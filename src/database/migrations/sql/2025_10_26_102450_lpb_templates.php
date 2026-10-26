<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('lpb_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique('uq_templates_slug');
            $table->text('description')->nullable();
            $table->string('view_path');
            $table->boolean('is_default')->default(false);
            $table->enum('status', ['active','inactive'])->default('active');
            $table->json('seo')->nullable();
            $table->timestamps();

            $table->index(['status'], 'idx_templates_status');
        });
    }

    public function down(): void {
        Schema::dropIfExists('lpb_templates');
    }
};
