<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Admin-editable settings stored in the database.
 * At boot (and before every queued job) the saved values are written over config('archive.*'),
 * so the rest of the code keeps using config() and needs no changes.
 * Anything not saved in the database falls back to .env / config/archive.php.
 */
class Setting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];

    private const CACHE_KEY = 'app_settings';

    /** key => label, group, type, min, max, unit, config key, multiplier (UI value -> config value), help */
    public const DEFS = [
        'ocr_dpi' => ['label' => 'OCR resolution (DPI)', 'group' => 'OCR', 'type' => 'int', 'min' => 72, 'max' => 600,
            'unit' => 'DPI', 'config' => 'archive.ocr_dpi', 'mult' => 1,
            'help' => 'Higher = better accuracy on small print but slower and uses more memory. 200 is a good default.'],
        'ocr_low_confidence' => ['label' => 'Low-confidence threshold', 'group' => 'OCR', 'type' => 'float', 'min' => 0, 'max' => 100,
            'unit' => '%', 'config' => 'archive.ocr_low_confidence', 'mult' => 1,
            'help' => 'Pages with OCR confidence below this are flagged as low confidence.'],
        'ocr_timeout' => ['label' => 'OCR timeout per run', 'group' => 'OCR', 'type' => 'int', 'min' => 60, 'max' => 86400,
            'unit' => 'seconds', 'config' => 'archive.ocr_timeout', 'mult' => 1,
            'help' => 'Maximum time one Python OCR run may take before it is stopped.'],
        'view_dpi' => ['label' => 'Page viewer resolution (DPI)', 'group' => 'OCR', 'type' => 'int', 'min' => 72, 'max' => 400,
            'unit' => 'DPI', 'config' => 'archive.view_dpi', 'mult' => 1,
            'help' => 'Quality of the page images shown in the viewer.'],
        'max_file_size' => ['label' => 'Max file size', 'group' => 'Upload limits', 'type' => 'int', 'min' => 1, 'max' => 10240,
            'unit' => 'MB', 'config' => 'archive.max_file_size', 'mult' => 1048576, 'php' => 'upload',
            'help' => 'Largest single document accepted.'],
        'max_zip_size' => ['label' => 'Max ZIP size', 'group' => 'Upload limits', 'type' => 'int', 'min' => 1, 'max' => 20480,
            'unit' => 'MB', 'config' => 'archive.max_zip_size', 'mult' => 1048576, 'php' => 'upload',
            'help' => 'Largest ZIP file accepted.'],
        'max_extracted_size' => ['label' => 'Max extracted size', 'group' => 'Upload limits', 'type' => 'int', 'min' => 1, 'max' => 102400,
            'unit' => 'MB', 'config' => 'archive.max_extracted_size', 'mult' => 1048576,
            'help' => 'Total size allowed after a ZIP is extracted.'],
        'max_files_per_zip' => ['label' => 'Max files per ZIP', 'group' => 'Upload limits', 'type' => 'int', 'min' => 1, 'max' => 100000,
            'unit' => 'files', 'config' => 'archive.max_files_per_zip', 'mult' => 1,
            'help' => 'Maximum number of files inside one ZIP.'],
    ];

    /** Original .env / config values, captured once before any override. */
    private static array $defaults = [];

    public static function defaults(): array
    {
        self::captureDefaults();
        return self::$defaults;
    }

    private static function captureDefaults(): void
    {
        if (self::$defaults) {
            return;
        }
        foreach (self::DEFS as $key => $d) {
            self::$defaults[$key] = config($d['config']) / $d['mult'];
        }
    }

    /** Saved values (key => UI value), cached across requests. */
    public static function allSaved(): array
    {
        try {
            return Cache::rememberForever(self::CACHE_KEY, fn () => self::query()->pluck('value', 'key')->all());
        } catch (\Throwable $e) {
            return []; // table not migrated yet
        }
    }

    public static function applyToConfig(): void
    {
        self::captureDefaults();
        foreach (self::allSaved() as $key => $value) {
            $d = self::DEFS[$key] ?? null;
            if ($d && $value !== null && $value !== '') {
                config([$d['config'] => ($d['type'] === 'float' ? (float) $value : (int) $value) * $d['mult']]);
            }
        }
    }

    /** Current effective value in UI units. */
    public static function current(string $key)
    {
        $saved = self::allSaved()[$key] ?? null;
        return ($saved !== null && $saved !== '') ? $saved + 0 : self::defaults()[$key];
    }

    public static function store(array $values): void
    {
        foreach ($values as $key => $value) {
            self::updateOrCreate(['key' => $key], ['value' => (string) $value]);
        }
        self::flush();
    }

    public static function resetAll(): void
    {
        self::query()->delete();
        self::flush();
    }

    private static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        self::applyToConfig();
    }

    /** Parse php.ini shorthand (e.g. 64M) to MB. */
    public static function iniMb(string $name): float
    {
        $v = trim((string) ini_get($name));
        if ($v === '' || $v === '-1') {
            return INF;
        }
        $n = (float) $v;
        return match (strtolower(substr($v, -1))) {
            'g' => $n * 1024,
            'm' => $n,
            'k' => $n / 1024,
            default => $n / 1048576,
        };
    }
}
