<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function getConnection(): ?string {
        return config('laravel-page-builder.connections.sql') ?: parent::getConnection();
    }

    public function up(): void {
        Schema::create('lpb_global_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value');
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('lpb_global_settings');
    }
};
