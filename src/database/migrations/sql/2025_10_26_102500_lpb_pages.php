<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('lpb_pages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('extra_css')->nullable();
            $table->text('extra_js')->nullable();
            $table->string('slug')->unique('uq_pages_slug');
            $table->enum('status', ['draft','published','archived'])->default('draft');
            $table->boolean('theme')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('lpb_pages');
    }
};
