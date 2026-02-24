<?php

namespace App\Services;

use App\Models\SystemLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class Logger
{
    /**
     * Log a DEBUG message (development use only)
     */
    public static function debug(string $module, string $action, string $message, array $meta = []): void
    {
        self::write('debug', $module, $action, $message, $meta);
    }

    /**
     * Log an INFO message (normal operations)
     * Use for: login, sale created, product added
     */
    public static function info(string $module, string $action, string $message, array $meta = []): void
    {
        self::write('info', $module, $action, $message, $meta);
    }

    /**
     * Log a WARN message (something to watch)
     * Use for: low stock, failed login attempt, slow response
     */
    public static function warn(string $module, string $action, string $message, array $meta = []): void
    {
        self::write('warn', $module, $action, $message, $meta);
    }

    /**
     * Log an ERROR message (something broke)
     * Use for: DB failure, unhandled exception, invalid data
     */
    public static function error(string $module, string $action, string $message, array $meta = []): void
    {
        self::write('error', $module, $action, $message, $meta);
    }

    /**
     * Log an AUDIT message (who did what - important for capstone!)
     * Use for: price changes, deletions, role changes, voided sales
     */
    public static function audit(string $module, string $action, string $message, array $meta = []): void
    {
        self::write('audit', $module, $action, $message, $meta);
    }

    /**
     * Core write method - saves log to database
     */
    private static function write(string $level, string $module, string $action, string $message, array $meta): void
    {
        try {
            SystemLog::create([
                'level'      => $level,
                'module'     => $module,
                'action'     => $action,
                'message'    => $message,
                'user_id'    => Auth::id(),
                'ip_address' => Request::ip(),
                'meta'       => !empty($meta) ? $meta : null,
            ]);
        } catch (\Exception $e) {
            // Fallback to Laravel's built-in log so we never lose logs
            \Log::error('SystemLog DB write failed: ' . $e->getMessage());
        }
    }
}
