<?php

namespace App\Providers;

use App\Models\Setting;
use App\Models\User;
use App\Services\QueueKicker;
use Illuminate\Pagination\Paginator;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // Settings saved by the admin (database) override .env / config defaults.
        Setting::applyToConfig();
        // A long-running queue worker picks up changed settings before each job.
        Queue::before(fn () => Setting::applyToConfig());

        // Whenever a web request queues OCR work, make sure a background worker is running.
        Event::listen(JobQueued::class, function () {
            if (!app()->runningInConsole()) {
                QueueKicker::kick();
            }
        });

        Gate::define('admin', fn (User $u) => $u->role === 'admin');
        Gate::define('manage', fn (User $u) => in_array($u->role, ['admin', 'manager']));
        // Print / download: admins + managers always; viewers only when granted.
        Gate::define('export', fn (User $u) => in_array($u->role, ['admin', 'manager']) || $u->can_export);
    }
}
