<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function getConnection(): ?string {
        return config('laravel-page-builder.connections.sql') ?: parent::getConnection();
    }

    public function up(): void {
        // Contents of media stored on the "database" disk, base64-encoded so
        // every driver stores them as text
        Schema::create('lpb_media_files', function (Blueprint $table) {
            $table->unsignedBigInteger('media_id')->primary();
            $table->longText('contents');
            $table->timestamps();

            $table->foreign('media_id', 'fk_media_files_media')
                  ->references('id')->on('lpb_media')
                  ->onDelete('cascade');
        });
    }

    public function down(): void {
        Schema::dropIfExists('lpb_media_files');
    }
};
