<?php

namespace App\Console\Commands;

use App\Services\QueueKicker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Background worker started automatically by QueueKicker. Runs until the queue is empty, then exits. */
class OcrWorker extends Command
{
    protected $signature = 'ocr:worker {--queue=default : imports (ZIP extraction) or default (OCR)}';
    protected $description = 'Process queued OCR jobs, then exit (single instance)';

    public function handle(): int
    {
        set_time_limit(0);
        $queue = (string) $this->option('queue');
        $fp = fopen(QueueKicker::lockFile($queue), 'c');
        if (!$fp || !flock($fp, LOCK_EX | LOCK_NB)) {
            return self::SUCCESS; // another worker is already running
        }

        try {
            // We hold the exclusive lock, so no other worker is alive: any job still "reserved"
            // belongs to a worker that was killed (server restart, crash). Make it runnable again.
            DB::table('jobs')->where('queue', $queue)->whereNotNull('reserved_at')->update(['reserved_at' => null, 'attempts' => 0]);

            do {
                $this->call('queue:work', ['--queue' => $queue, '--stop-when-empty' => true, '--timeout' => 0, '--tries' => 1, '--sleep' => 1]);
                // a job may have been queued while we were finishing
                $more = config('queue.default') === 'database'
                    && DB::table('jobs')->where('queue', $queue)->whereNull('reserved_at')->exists();
            } while ($more);
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }

        // Extraction is finished and every file is saved: this same process now continues with the OCR queue
        // (it simply exits if an OCR worker is already running). No new process has to be started.
        if ($queue === 'imports') {
            $this->call('ocr:worker', ['--queue' => 'default']);
        }
        return self::SUCCESS;
    }
}
