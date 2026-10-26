<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('lpb_menu_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('menu_id');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('label');
            $table->string('url');
            $table->string('target')->default('_self');
            $table->unsignedSmallInteger('order')->default(0);
            $table->timestamps();

            $table->foreign('menu_id', 'fk_menu_items_menu')
                  ->references('id')->on('lpb_menus')
                  ->onDelete('cascade');

            $table->foreign('parent_id', 'fk_menu_items_parent')
                  ->references('id')->on('lpb_menu_items')
                  ->onDelete('set null');

            $table->index(['menu_id', 'parent_id', 'order'], 'idx_menu_items_menu_parent_order');
        });
    }

    public function down(): void {
        Schema::dropIfExists('lpb_menu_items');
    }
};
