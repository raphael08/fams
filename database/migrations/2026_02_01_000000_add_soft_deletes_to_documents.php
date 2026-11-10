<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $t) {
            $t->softDeletes();                                   // deleted_at: partial delete (hidden, files kept)
            $t->unsignedBigInteger('deleted_by')->nullable();    // who deleted it
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $t) {
            $t->dropColumn(['deleted_at', 'deleted_by']);
        });
    }
};
