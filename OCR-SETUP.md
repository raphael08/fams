# OCR setup (no tesseract.exe, no manual `queue:work`)

* **Engine**: RapidOCR (pip package, models bundled): `pip install -r python/requirements.txt`
* **Upload**: the file is saved and the page returns immediately; OCR is added to the database queue (`jobs` table).
* **Worker**: starts itself, detached, (a) right after a job is queued and (b) whenever anyone opens the site while jobs are waiting (checked after the response is sent, at most every 10 s). A file lock (`storage/framework/ocr-worker.lock`) guarantees only one worker runs; it exits when the queue is empty.
* If the server is restarted mid-OCR, the next worker start releases the orphaned job and OCR resumes.

## .env
    QUEUE_CONNECTION=database
    DB_QUEUE_RETRY_AFTER=14500
    PYTHON_PATH=/usr/bin/python3        # or the full path to python.exe
    PHP_CLI=/usr/bin/php                # optional; set it if the worker does not start (full path to php.exe on Windows)

## Requirements
`php artisan migrate` (creates the jobs table), PHP allowed to use `exec` / `popen`, `storage/` writable.
Check the OCR engine with `php artisan ocr:check`.


## Upgrade notes (documents delete, DB settings, DataTables)
* Run `php artisan migrate` once (adds `deleted_at`/`deleted_by` to documents and the `settings` table).
* Settings (OCR DPI, thresholds, upload limits) are edited in **Settings** and stored in the database; `.env` values are only the defaults.
* DataTables is bundled in `public/vendor/datatables` (no CDN needed for tables / export buttons).
