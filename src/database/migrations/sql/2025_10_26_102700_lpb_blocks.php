<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function getConnection(): ?string {
        return config('laravel-page-builder.connections.sql') ?: parent::getConnection();
    }

    public function up(): void {
        Schema::create('lpb_blocks', function (Blueprint $table) {
            $table->id();
            $table->uuid('page_id');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('type');
            $table->unsignedSmallInteger('order')->default(0);
            $table->timestamps();

            $table->foreign('page_id', 'fk_blocks_page')
                  ->references('id')->on('lpb_pages')
                  ->onDelete('cascade');

            $table->foreign('parent_id', 'fk_blocks_parent')
                  ->references('id')->on('lpb_blocks')
                  ->onDelete('set null');

            $table->index(['page_id', 'parent_id', 'order'], 'idx_blocks_page_parent_order');
        });
    }

    public function down(): void {
        Schema::dropIfExists('lpb_blocks');
    }
};
