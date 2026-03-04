<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\TechnicianReview;
use App\Services\Logger;
use Illuminate\Http\Request;

class TechnicianController extends Controller
{
    /**
     * GET /api/technician/queue
     * Returns all sales with their review status
     */
    public function queue(Request $request)
    {
        $status = $request->query('status', 'pending'); // pending or checked or all

        $query = Sale::with(['items', 'user'])
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc');

        // Filter by review status
        if ($status === 'pending') {
            $query->whereDoesntHave('technicianReview')
                  ->orWhereHas('technicianReview', fn($q) => $q->where('status', 'pending'));
        } elseif ($status === 'checked') {
            $query->whereHas('technicianReview', fn($q) => $q->where('status', 'checked'));
        }

        $sales = $query->paginate(20);

        // Attach review info to each sale
        $sales->getCollection()->transform(function ($sale) {
            $review = $sale->technicianReview;
            $sale->review_status = $review?->status ?? 'pending';
            $sale->review_notes = $review?->notes;
            $sale->reviewed_at = $review?->reviewed_at;
            $sale->reviewed_by = $review?->reviewer?->name;
            return $sale;
        });

        return response()->json([
            'success' => true,
            'data'    => $sales,
        ]);
    }

    /**
     * POST /api/technician/review/{saleId}
     * Mark a sale as checked
     */
    public function review(Request $request, $saleId)
    {
        $request->validate([
            'status' => 'required|in:pending,checked',
            'notes'  => 'nullable|string|max:500',
        ]);

        $sale = Sale::findOrFail($saleId);

        $review = TechnicianReview::updateOrCreate(
            ['sale_id' => $sale->id],
            [
                'reviewed_by' => $request->user()->id,
                'status'      => $request->status,
                'notes'       => $request->notes,
                'reviewed_at' => $request->status === 'checked' ? now() : null,
            ]
        );

        Logger::info('technician', 'review', "Sale {$sale->sale_number} marked as {$request->status}", [
            'sale_id'     => $sale->id,
            'reviewed_by' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Sale marked as {$request->status}.",
            'data'    => $review,
        ]);
    }
}
