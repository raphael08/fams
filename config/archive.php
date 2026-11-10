<?php

return [
    'python'         => env('ARCHIVE_PYTHON', 'python3'),
    'ocr_dpi'        => (int) env('OCR_DPI', 200),
    'view_dpi'       => (int) env('VIEW_DPI', 150),
    'ocr_low_confidence' => (float) env('OCR_LOW_CONFIDENCE', 70),
    'ocr_timeout'    => (int) env('OCR_TIMEOUT', 14400),

    'allowed_extensions' => ['pdf', 'jpg', 'jpeg', 'png', 'tif', 'tiff'],
    'mime_map' => [
        'pdf'  => ['application/pdf'],
        'jpg'  => ['image/jpeg'], 'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'tif'  => ['image/tiff'], 'tiff' => ['image/tiff'],
    ],

    // Limits (MB unless noted)
    'max_file_size'      => (int) env('MAX_FILE_SIZE', 200) * 1048576,
    'max_zip_size'       => (int) env('MAX_ZIP_SIZE', 500) * 1048576,
    'max_extracted_size' => (int) env('MAX_EXTRACTED_SIZE', 2048) * 1048576,
    'max_files_per_zip'  => (int) env('MAX_FILES_PER_ZIP', 500),
    'python' => env('PYTHON_PATH', 'python3'),
    'php_cli' => env('PHP_CLI'),          // full path to php.exe / php for the auto-started OCR worker
];
