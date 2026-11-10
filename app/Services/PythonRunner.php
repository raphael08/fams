<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

/** Runs the helper scripts in /python. Arguments are passed as an array (no shell), so no injection. */
class PythonRunner
{
    private static function make(string $script, array $args, int $timeout): Process
    {
        $pythonBinary = config('archive.python', 'python3');
        $scriptPath = base_path('python/' . $script);

        $cmd = array_merge([$pythonBinary, $scriptPath], array_map('strval', $args));

        return new Process(
            command: $cmd,
            cwd: base_path('python'),
            env: array_filter([
                'PYTHONUNBUFFERED' => '1',
                'PYTHONIOENCODING' => 'utf-8',
            ]),
            input: null,
            timeout: $timeout
        );
    }

    /** Run and return stdout. */
    public static function run(string $script, array $args, int $timeout = 600, ?string $input = null): string
    {
        $p = self::make($script, $args, $timeout);

        if ($input !== null) {
            $p->setInput($input);
        }

        $p->run();

        if (!$p->isSuccessful()) {
            throw new RuntimeException(self::lastLine($p->getErrorOutput()) ?: "$script failed (exit {$p->getExitCode()})");
        }

        return $p->getOutput();
    }

    /** Run and hand each stdout line to $onLine as it arrives (constant memory for 1000-page PDFs). */
    public static function stream(string $script, array $args, callable $onLine): void
    {
        $p = self::make($script, $args, config('archive.ocr_timeout', 600));
        $p->disableOutput();

        $buffer = '';
        $err = '';

        $p->run(function ($type, $data) use (&$buffer, &$err, $onLine) {
            if ($type === Process::ERR) {
                $err = substr($err . $data, -2000);
                return;
            }

            $buffer .= $data;
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $pos));
                $buffer = substr($buffer, $pos + 1);

                if ($line !== '') {
                    $onLine($line);
                }
            }
        });

        if (!$p->isSuccessful()) {
            throw new RuntimeException(self::lastLine($err) ?: "$script failed (exit {$p->getExitCode()})");
        }
    }

    private static function lastLine(string $s): string
    {
        $lines = array_filter(array_map('trim', explode("\n", $s)));
        return substr((string) end($lines), 0, 500);
    }
}
