<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function getConnection(): ?string {
        return config('laravel-page-builder.connections.sql') ?: parent::getConnection();
    }

    public function up(): void {
        Schema::create('lpb_widget_instances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('block_id');
            $table->string('widget');
            $table->json('config');
            $table->unsignedSmallInteger('order')->default(0);
            $table->timestamps();

            $table->foreign('block_id', 'fk_widget_instances_block')
                  ->references('id')->on('lpb_blocks')
                  ->onDelete('cascade');

            $table->index(['block_id', 'order'], 'idx_widget_instances_block_order');
        });
    }

    public function down(): void {
        Schema::dropIfExists('lpb_widget_instances');
    }
};
