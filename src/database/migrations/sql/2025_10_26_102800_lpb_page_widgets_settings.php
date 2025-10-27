<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('lpb_page_widget_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->text('value');
            $table->unsignedBigInteger('lpb_page_widget_id');
            $table->timestamps();

            $table->foreign('lpb_page_widget_id', 'fk_widget_setting_widget')
                  ->references('id')->on('lpb_page_widgets')
                  ->onDelete('cascade');

            $table->unique(['lpb_page_widget_id','key'], 'uq_widget_setting');
            $table->index(['lpb_page_widget_id'], 'idx_widget_setting_widget');
        });
    }

    public function down(): void {
        Schema::dropIfExists('lpb_page_widgets_settings');
    }
};
