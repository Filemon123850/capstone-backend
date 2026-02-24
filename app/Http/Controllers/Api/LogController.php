<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemLog;
use Illuminate\Http\Request;

class LogController extends Controller
{
    /**
     * GET /api/logs
     * View system logs with filters (admin only)
     */
    public function index(Request $request)
    {
        $query = SystemLog::with('user');

        if ($request->level) {
            $query->where('level', $request->level);
        }

        if ($request->module) {
            $query->where('module', $request->module);
        }

        if ($request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->search) {
            $query->where('message', 'like', "%{$request->search}%");
        }

        $logs = $query->orderBy('created_at', 'desc')->paginate(50);

        return response()->json([
            'success' => true,
            'data'    => $logs,
        ]);
    }

    /**
     * GET /api/logs/export
     * Export logs as CSV for documentation
     */
    public function export(Request $request)
    {
        $logs = SystemLog::with('user')
            ->when($request->level, fn($q) => $q->where('level', $request->level))
            ->when($request->date_from, fn($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->date_to, fn($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->orderBy('created_at', 'desc')
            ->limit(5000)
            ->get();

        $csv = "ID,Level,Module,Action,Message,User,IP Address,Date\n";
        foreach ($logs as $log) {
            $csv .= implode(',', [
                $log->id,
                strtoupper($log->level),
                $log->module,
                $log->action,
                '"' . str_replace('"', '""', $log->message) . '"',
                $log->user?->name ?? 'System',
                $log->ip_address,
                $log->created_at->format('Y-m-d H:i:s'),
            ]) . "\n";
        }

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="system_logs_' . date('Ymd') . '.csv"',
        ]);
    }
}
