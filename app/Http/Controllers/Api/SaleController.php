<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Product;
use App\Models\InventoryLog;
use App\Services\Logger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{
    /**
     * GET /api/sales
     * List all sales with filters
     */
    public function index(Request $request)
    {
        $query = Sale::with(['user', 'items.product']);

        if ($request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->cashier_id) {
            $query->where('user_id', $request->cashier_id);
        }

        $sales = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'data'    => $sales,
        ]);
    }

    /**
     * POST /api/sales
     * Create a new sale (Point of Sale)
     */
    public function store(Request $request)
    {
        $request->validate([
            'items'                 => 'required|array|min:1',
            'items.*.product_id'    => 'required|exists:products,id',
            'items.*.quantity'      => 'required|integer|min:1',
            'items.*.discount'      => 'nullable|numeric|min:0',
            'payment_method'        => 'required|in:cash,gcash,credit_card,others',
            'amount_tendered'       => 'required|numeric|min:0',
            'customer_name'         => 'nullable|string',
            'notes'                 => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $subtotal = 0;
            $saleItems = [];

            // Process each item
            foreach ($request->items as $item) {
                $product = Product::findOrFail($item['product_id']);

                // Check stock availability
                if ($product->stock_quantity < $item['quantity']) {
                    return response()->json([
                        'success' => false,
                        'message' => "Insufficient stock for: {$product->name}. Available: {$product->stock_quantity}",
                    ], 422);
                }

                $itemSubtotal = ($product->selling_price * $item['quantity']) - ($item['discount'] ?? 0);
                $subtotal += $itemSubtotal;

                $saleItems[] = [
                    'product'      => $product,
                    'quantity'     => $item['quantity'],
                    'unit_price'   => $product->selling_price,
                    'discount'     => $item['discount'] ?? 0,
                    'subtotal'     => $itemSubtotal,
                ];
            }

            $totalAmount = $subtotal; // Add tax logic here if needed
            $change = $request->amount_tendered - $totalAmount;

            if ($change < 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Amount tendered is less than total amount.',
                ], 422);
            }

            // Generate sale number
            $saleNumber = 'SALE-' . date('Ymd') . '-' . str_pad(Sale::whereDate('created_at', today())->count() + 1, 4, '0', STR_PAD_LEFT);

            // Create the sale
            $sale = Sale::create([
                'sale_number'    => $saleNumber,
                'user_id'        => auth()->id(),
                'subtotal'       => $subtotal,
                'total_amount'   => $totalAmount,
                'amount_tendered'=> $request->amount_tendered,
                'change_amount'  => $change,
                'payment_method' => $request->payment_method,
                'customer_name'  => $request->customer_name,
                'notes'          => $request->notes,
                'status'         => 'completed',
            ]);

            // Save sale items and deduct stock
            foreach ($saleItems as $item) {
                SaleItem::create([
                    'sale_id'      => $sale->id,
                    'product_id'   => $item['product']->id,
                    'product_name' => $item['product']->name,
                    'unit_price'   => $item['unit_price'],
                    'quantity'     => $item['quantity'],
                    'discount'     => $item['discount'],
                    'subtotal'     => $item['subtotal'],
                ]);

                // Deduct stock and log inventory change
                $before = $item['product']->stock_quantity;
                $item['product']->decrement('stock_quantity', $item['quantity']);
                $after = $item['product']->fresh()->stock_quantity;

                InventoryLog::create([
                    'product_id'      => $item['product']->id,
                    'user_id'         => auth()->id(),
                    'type'            => 'sale',
                    'quantity_before' => $before,
                    'quantity_change' => -$item['quantity'],
                    'quantity_after'  => $after,
                    'reason'          => "Sale #{$saleNumber}",
                ]);

                // Trigger low stock warning
                if ($after <= $item['product']->low_stock_threshold) {
                    Logger::warn('inventory', 'low_stock', "Low stock after sale: {$item['product']->name}", [
                        'product_id' => $item['product']->id,
                        'stock'      => $after,
                        'threshold'  => $item['product']->low_stock_threshold,
                    ]);
                }
            }

            DB::commit();

            Logger::info('sales', 'sale_created', "Sale completed: {$saleNumber}", [
                'sale_id'      => $sale->id,
                'sale_number'  => $saleNumber,
                'total_amount' => $totalAmount,
                'item_count'   => count($saleItems),
                'payment'      => $request->payment_method,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Sale recorded successfully.',
                'data'    => $sale->load('items.product'),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            Logger::error('sales', 'sale_failed', 'Sale transaction failed: ' . $e->getMessage(), [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Sale failed. Please try again.',
            ], 500);
        }
    }

    /**
     * GET /api/sales/{id}
     */
    public function show(Sale $sale)
    {
        return response()->json([
            'success' => true,
            'data'    => $sale->load(['user', 'items.product']),
        ]);
    }

    /**
     * POST /api/sales/{id}/void
     * Void a sale (admin only)
     */
    public function void(Request $request, Sale $sale)
    {
        $request->validate([
            'reason' => 'required|string',
        ]);

        if ($sale->status !== 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Only completed sales can be voided.',
            ], 422);
        }

        $sale->update(['status' => 'voided', 'notes' => 'VOIDED: ' . $request->reason]);

        // Restore stock for each item
        foreach ($sale->items as $item) {
            $before = $item->product->stock_quantity;
            $item->product->increment('stock_quantity', $item->quantity);
            $after = $item->product->fresh()->stock_quantity;

            InventoryLog::create([
                'product_id'      => $item->product_id,
                'user_id'         => auth()->id(),
                'type'            => 'return',
                'quantity_before' => $before,
                'quantity_change' => $item->quantity,
                'quantity_after'  => $after,
                'reason'          => "Voided Sale #{$sale->sale_number}",
            ]);
        }

        Logger::audit('sales', 'sale_voided', "Sale voided: {$sale->sale_number}", [
            'sale_id'     => $sale->id,
            'sale_number' => $sale->sale_number,
            'total'       => $sale->total_amount,
            'reason'      => $request->reason,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Sale voided successfully.',
        ]);
    }

    /**
     * GET /api/sales/summary
     * Daily/weekly/monthly summary for analytics
     */
    public function summary(Request $request)
    {
        $period = $request->period ?? 'today'; // today, week, month

        $query = Sale::where('status', 'completed');

        switch ($period) {
            case 'today':
                $query->whereDate('created_at', today());
                break;
            case 'week':
                $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                break;
            case 'month':
                $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year);
                break;
        }

        $summary = [
            'total_sales'    => $query->count(),
            'total_revenue'  => $query->sum('total_amount'),
            'average_sale'   => $query->avg('total_amount'),
            'top_products'   => SaleItem::select('product_name', DB::raw('SUM(quantity) as total_qty'), DB::raw('SUM(subtotal) as total_revenue'))
                                    ->whereIn('sale_id', $query->pluck('id'))
                                    ->groupBy('product_name')
                                    ->orderByDesc('total_qty')
                                    ->limit(5)
                                    ->get(),
        ];

        return response()->json([
            'success' => true,
            'data'    => $summary,
        ]);
    }
}
