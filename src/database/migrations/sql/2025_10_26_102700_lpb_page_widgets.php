<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('lpb_page_widgets', function (Blueprint $table) {
            $table->id();
            $table->integer('position_x');
            $table->integer('position_y');
            $table->unsignedBigInteger('child_id')->nullable();
            $table->string('widget');
            $table->uuid('lpb_page_id');
            $table->timestamps();

            $table->foreign('child_id', 'fk_widgets_child')
                  ->references('id')->on('lpb_page_widgets')
                  ->onDelete('set null');

            $table->foreign('lpb_page_id', 'fk_widgets_page')
                  ->references('id')->on('lpb_pages')
                  ->onDelete('cascade');

            $table->index(['child_id'], 'idx_widgets_child');
        });

        DB::statement("ALTER TABLE lpb_page_widgets
            ADD CONSTRAINT chk_widgets_position_x CHECK (position_x >= 0)");
        DB::statement("ALTER TABLE lpb_page_widgets
            ADD CONSTRAINT chk_widgets_position_y CHECK (position_y >= 0)");
    }

    public function down(): void {
        Schema::dropIfExists('lpb_page_widgets');
    }
};
