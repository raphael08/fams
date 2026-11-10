<?php

namespace App\Services;

/**
 * Starts `php artisan ocr:worker` as a hidden background process so OCR runs by itself
 * after an upload / retry. The worker takes a file lock, so calling kick() many times
 * never starts more than one worker; extra processes exit immediately.
 */
class QueueKicker
{
    /** 'imports' = ZIP extraction (fast, must never wait behind OCR); 'default' = OCR. */
    public const QUEUES = ['imports', 'default'];

    /** Start a worker for every queue that has waiting jobs and no worker running. */
    public static function kick(): void
    {
        foreach (self::QUEUES as $queue) {
            try {
                if (config('queue.default') !== 'database' || self::workerRunning($queue)) {
                    continue;
                }
                if (!\Illuminate\Support\Facades\DB::table('jobs')->where('queue', $queue)->exists()) {
                    continue;
                }
                $php = self::phpBinary();
                $artisan = base_path('artisan');

                if (PHP_OS_FAMILY === 'Windows') {
                    $cmd = 'start "" /B "' . $php . '" "' . $artisan . '" ocr:worker --queue=' . $queue . ' > NUL 2>&1';
                    pclose(popen($cmd, 'r'));
                } else {
                    exec('nohup ' . escapeshellarg($php) . ' ' . escapeshellarg($artisan) . ' ocr:worker --queue=' . $queue . ' > /dev/null 2>&1 &');
                }
            } catch (\Throwable $e) {
                logger()->warning("Could not start the $queue worker: " . $e->getMessage());
            }
        }
    }

    /** True when another process holds that queue's worker lock. */
    public static function workerRunning(string $queue = 'default'): bool
    {
        $fp = @fopen(self::lockFile($queue), 'c');
        if (!$fp) {
            return false;
        }
        $free = flock($fp, LOCK_EX | LOCK_NB);
        if ($free) {
            flock($fp, LOCK_UN);
        }
        fclose($fp);
        return !$free;
    }

    public static function lockFile(string $queue = 'default'): string
    {
        return storage_path($queue === 'default' ? 'framework/ocr-worker.lock' : "framework/ocr-worker-$queue.lock");
    }

    /** The web server's PHP_BINARY can be httpd.exe / php-fpm, so find the real CLI php. */
    private static function phpBinary(): string
    {
        if ($p = config('archive.php_cli')) {
            return $p;
        }
        $ext = PHP_OS_FAMILY === 'Windows' ? '.exe' : '';
        if (PHP_SAPI === 'cli' || preg_match('~[\\\\/]php(\.exe)?$~i', PHP_BINARY)) {
            return PHP_BINARY;
        }
        $dirs = array_filter([dirname((string) php_ini_loaded_file()), PHP_BINDIR]);
        foreach ($dirs as $d) {
            if (is_file("$d/php$ext")) {
                return "$d/php$ext";
            }
        }
        return 'php';
    }
}
