<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('username')->nullable()->unique()->after('name');
            $t->string('role', 20)->default('viewer')->after('password');   // admin | manager | viewer
            $t->boolean('can_export')->default(false)->after('role');       // viewer print/download permission
            $t->boolean('is_active')->default(true)->after('can_export');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['username', 'role', 'can_export', 'is_active']));
    }
};
