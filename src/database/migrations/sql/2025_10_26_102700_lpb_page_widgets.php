<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::statement("CREATE TABLE lpb_page_widgets (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                position_x INTEGER NOT NULL,
                position_y INTEGER NOT NULL,
                parent_id INTEGER,
                \"order\" INTEGER NOT NULL DEFAULT 0,
                widget VARCHAR(255) NOT NULL,
                lpb_page_id CHAR(36) NOT NULL,
                created_at DATETIME,
                updated_at DATETIME,
                FOREIGN KEY (parent_id) REFERENCES lpb_page_widgets(id) ON DELETE SET NULL,
                FOREIGN KEY (lpb_page_id) REFERENCES lpb_pages(id) ON DELETE CASCADE,
                CHECK (position_x >= 0),
                CHECK (position_y >= 0),
                CHECK (\"order\" >= 0)
            )");

            DB::statement("CREATE INDEX idx_widgets_parent ON lpb_page_widgets(parent_id)");
        } else {
            Schema::create('lpb_page_widgets', function (Blueprint $table) {
                $table->id();
                $table->integer('position_x');
                $table->integer('position_y');
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->unsignedInteger('order')->default(0);
                $table->string('widget');
                $table->uuid('lpb_page_id');
                $table->timestamps();

                $table->foreign('parent_id', 'fk_widgets_parent')
                      ->references('id')->on('lpb_page_widgets')
                      ->onDelete('set null');

                $table->foreign('lpb_page_id', 'fk_widgets_page')
                      ->references('id')->on('lpb_pages')
                      ->onDelete('cascade');

                $table->index(['parent_id'], 'idx_widgets_parent');
            });

            // Blueprint has no CHECK constraint API, so add them with raw SQL
            DB::statement('ALTER TABLE lpb_page_widgets ADD CONSTRAINT chk_widgets_position_x CHECK (position_x >= 0)');
            DB::statement('ALTER TABLE lpb_page_widgets ADD CONSTRAINT chk_widgets_position_y CHECK (position_y >= 0)');
        }
    }

    public function down(): void {
        Schema::dropIfExists('lpb_page_widgets');
    }
};
