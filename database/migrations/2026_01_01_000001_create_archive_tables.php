<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('document_categories', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->timestamps();
        });

        Schema::create('document_types', function (Blueprint $t) {
            $t->id();
            $t->foreignId('document_category_id')->nullable()->constrained('document_categories')->nullOnDelete();
            $t->string('name');
            $t->timestamps();
            $t->unique(['document_category_id', 'name']);
        });

        Schema::create('document_parties', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->timestamps();
        });

        Schema::create('zip_imports', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->string('original_filename');
            $t->string('zip_path');
            $t->unsignedInteger('total_files')->default(0);
            $t->unsignedInteger('processed_files')->default(0);
            $t->unsignedInteger('imported_files')->default(0);
            $t->unsignedInteger('duplicate_files')->default(0);
            $t->unsignedInteger('unsupported_files')->default(0);
            $t->unsignedInteger('failed_files')->default(0);
            $t->string('status', 30)->default('queued');
            $t->text('message')->nullable();
            $t->longText('report')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->timestamp('completed_at')->nullable();
        });

        Schema::create('documents', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->string('original_filename');
            $t->string('storage_path');                       // relative to storage/app
            $t->string('mime_type', 100);
            $t->unsignedBigInteger('file_size');
            $t->char('sha256_hash', 64)->unique();
            $t->unsignedInteger('page_count')->default(0);
            $t->string('title')->nullable();
            $t->foreignId('document_category_id')->nullable()->constrained('document_categories')->nullOnDelete();
            $t->foreignId('document_type_id')->nullable()->constrained('document_types')->nullOnDelete();
            $t->foreignId('document_party_id')->nullable()->constrained('document_parties')->nullOnDelete();
            $t->unsignedSmallInteger('year')->nullable();
            $t->date('document_date')->nullable();
            $t->string('reference_number')->nullable();
            $t->string('document_number')->nullable();
            $t->string('metadata_source', 10)->default('auto'); // auto | manual (manual is authoritative)
            $t->string('ocr_status', 30)->default('pending');
            $t->unsignedTinyInteger('ocr_progress')->default(0);
            $t->text('ocr_error')->nullable();
            $t->foreignId('zip_import_id')->nullable()->constrained('zip_imports')->nullOnDelete();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index('ocr_status');
            $t->index('year');
            $t->index('created_at');
        });

        Schema::create('document_pages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $t->unsignedInteger('page_number');
            $t->longText('ocr_text')->nullable();
            $t->decimal('ocr_confidence', 5, 2)->nullable();
            $t->string('ocr_status', 20)->default('pending');
            $t->string('ocr_error', 500)->nullable();
            $t->timestamps();
            $t->unique(['document_id', 'page_number']);
        });

        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->nullable()->index();
            $t->string('action', 50)->index();
            $t->unsignedBigInteger('document_id')->nullable()->index(); // no FK: logs outlive documents
            $t->string('page_numbers', 500)->nullable();
            $t->text('description')->nullable();
            $t->string('ip_address', 45)->nullable();
            $t->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'document_pages', 'documents', 'zip_imports', 'document_parties', 'document_types', 'document_categories'] as $tb) {
            Schema::dropIfExists($tb);
        }
    }
};
