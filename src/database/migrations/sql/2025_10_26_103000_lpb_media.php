<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function getConnection(): ?string {
        return config('laravel-page-builder.connections.sql') ?: parent::getConnection();
    }

    public function up(): void {
        Schema::create('lpb_media', function (Blueprint $table) {
            $table->id();
            $table->string('disk');
            $table->string('path');
            $table->string('filename');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->json('meta')->nullable();
            $table->string('collection')->nullable();
            $table->timestamps();

            $table->unique(['disk', 'path'], 'uq_media_disk_path');
            $table->index(['mime_type'], 'idx_media_mime_type');
            $table->index(['collection'], 'idx_media_collection');
        });
    }

    public function down(): void {
        Schema::dropIfExists('lpb_media');
    }
};
