<?php

namespace App\Console\Commands;

use App\Services\PythonRunner;
use Illuminate\Console\Command;

/** php artisan ocr:check  -> shows exactly what is missing for OCR on this machine. */
class OcrCheck extends Command
{
    protected $signature = 'ocr:check';
    protected $description = 'Check Python, PyMuPDF and the OCR engine';

    public function handle(): int
    {
        $this->line('Python used by Laravel: ' . config('archive.python'));
        try {
            $this->line(PythonRunner::run('check_setup.py', [], 60));
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
