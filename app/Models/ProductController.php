<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\InventoryLog;
use App\Services\Logger;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * GET /api/products
     * List all products with optional search & filter
     */
    public function index(Request $request)
    {
        $query = Product::with('category')->where('is_active', true);

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('sku', 'like', "%{$request->search}%");
            });
        }

        if ($request->category_id) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->low_stock) {
            $query->whereRaw('stock_quantity <= low_stock_threshold');
        }

        $products = $query->orderBy('name')->paginate(20);

        return response()->json([
            'success' => true,
            'data'    => $products,
        ]);
    }

    /**
     * POST /api/products
     * Create a new product
     */
    public function store(Request $request)
    {
        $request->validate([
            'name'          => 'required|string|max:255',
            'sku'           => 'required|string|unique:products,sku',
            'category_id'   => 'required|exists:categories,id',
            'selling_price' => 'required|numeric|min:0',
            'cost_price'    => 'nullable|numeric|min:0',
            'stock_quantity'=> 'required|integer|min:0',
            'low_stock_threshold' => 'nullable|integer|min:0',
            'unit'          => 'nullable|string',
        ]);

        $product = Product::create($request->all());

        Logger::audit('inventory', 'product_created', "New product added: {$product->name}", [
            'product_id' => $product->id,
            'sku'        => $product->sku,
            'price'      => $product->selling_price,
            'stock'      => $product->stock_quantity,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully.',
            'data'    => $product->load('category'),
        ], 201);
    }

    /**
     * GET /api/products/{id}
     */
    public function show(Product $product)
    {
        return response()->json([
            'success' => true,
            'data'    => $product->load('category'),
        ]);
    }

    /**
     * PUT /api/products/{id}
     * Update product details
     */
    public function update(Request $request, Product $product)
    {
        $request->validate([
            'name'          => 'sometimes|string|max:255',
            'selling_price' => 'sometimes|numeric|min:0',
            'cost_price'    => 'sometimes|numeric|min:0',
            'low_stock_threshold' => 'sometimes|integer|min:0',
        ]);

        $oldPrice = $product->selling_price;
        $product->update($request->all());

        // Log price change separately for audit trail
        if ($request->has('selling_price') && $oldPrice != $request->selling_price) {
            Logger::audit('inventory', 'price_updated', "Price changed for: {$product->name}", [
                'product_id' => $product->id,
                'old_price'  => $oldPrice,
                'new_price'  => $request->selling_price,
            ]);
        } else {
            Logger::audit('inventory', 'product_updated', "Product updated: {$product->name}", [
                'product_id' => $product->id,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully.',
            'data'    => $product->load('category'),
        ]);
    }

    /**
     * DELETE /api/products/{id}
     * Soft delete a product
     */
    public function destroy(Product $product)
    {
        Logger::audit('inventory', 'product_deleted', "Product deleted: {$product->name}", [
            'product_id' => $product->id,
            'sku'        => $product->sku,
        ]);

        $product->delete(); // soft delete

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully.',
        ]);
    }

    /**
     * POST /api/products/{id}/restock
     * Manually adjust stock (restock, damage, return)
     */
    public function adjustStock(Request $request, Product $product)
    {
        $request->validate([
            'type'     => 'required|in:restock,adjustment,damage,return',
            'quantity' => 'required|integer|not_in:0',
            'reason'   => 'nullable|string',
        ]);

        $before = $product->stock_quantity;
        $product->increment('stock_quantity', $request->quantity);
        $after = $product->fresh()->stock_quantity;

        // Save inventory log
        InventoryLog::create([
            'product_id'       => $product->id,
            'user_id'          => auth()->id(),
            'type'             => $request->type,
            'quantity_before'  => $before,
            'quantity_change'  => $request->quantity,
            'quantity_after'   => $after,
            'reason'           => $request->reason,
        ]);

        Logger::audit('inventory', 'stock_adjusted', "Stock adjusted for: {$product->name}", [
            'product_id' => $product->id,
            'type'       => $request->type,
            'before'     => $before,
            'change'     => $request->quantity,
            'after'      => $after,
        ]);

        // Warn if still low after restock
        if ($after <= $product->low_stock_threshold) {
            Logger::warn('inventory', 'low_stock', "Low stock alert: {$product->name}", [
                'product_id' => $product->id,
                'stock'      => $after,
                'threshold'  => $product->low_stock_threshold,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Stock adjusted successfully.',
            'data'    => ['stock_quantity' => $after],
        ]);
    }
}
