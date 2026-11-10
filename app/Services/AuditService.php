<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Document;
use Illuminate\Support\Facades\Auth;

class AuditService
{
    public static function log(string $action, ?Document $doc = null, array|string|null $pages = null, ?string $description = null, ?int $userId = null): void
    {
        AuditLog::create([
            'user_id' => $userId ?? Auth::id(),
            'action' => $action,
            'document_id' => $doc?->id,
            'page_numbers' => is_array($pages) ? substr(implode(',', $pages), 0, 500) : $pages,
            'description' => $description ? substr($description, 0, 2000) : null,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }
}
