<?php

namespace App\Http\Middleware;

use App\Services\QueueKicker;
use Closure;
use Illuminate\Http\Request;

/**
 * Whenever someone opens the site: if OCR jobs are waiting and no worker is running, start one.
 * Runs in terminate() (after the response is sent) and is throttled, so pages are never slowed down.
 */
class EnsureOcrWorker
{
    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }

    public function terminate(Request $request, $response): void
    {
        try {
            if (config('queue.default') !== 'database') {
                return;
            }
            $stamp = storage_path('framework/ocr-worker-check');
            if (is_file($stamp) && time() - filemtime($stamp) < 10) {
                return; // checked a few seconds ago
            }
            @touch($stamp);

            QueueKicker::kick(); // starts only the workers that are missing and have jobs waiting
        } catch (\Throwable $e) {
            // never break a page because of the worker check
        }
    }
}
