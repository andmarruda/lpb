<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // Create table with CHECK constraint for SQLite compatibility
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::statement("CREATE TABLE lpb_metatags (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                name VARCHAR(255),
                content TEXT NOT NULL,
                property VARCHAR(255),
                lpb_page_id CHAR(36) NOT NULL,
                created_at DATETIME,
                updated_at DATETIME,
                FOREIGN KEY (lpb_page_id) REFERENCES lpb_pages(id) ON DELETE CASCADE,
                CHECK ((name IS NOT NULL) != (property IS NOT NULL)),
                UNIQUE(lpb_page_id, name),
                UNIQUE(lpb_page_id, property)
            )");

            DB::statement("CREATE INDEX idx_metatags_page ON lpb_metatags(lpb_page_id)");
        } else {
            Schema::create('lpb_metatags', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->text('content');
                $table->string('property')->nullable();
                $table->uuid('lpb_page_id');
                $table->timestamps();

                $table->foreign('lpb_page_id', 'fk_metatags_page')
                      ->references('id')->on('lpb_pages')
                      ->onDelete('cascade');

                $table->unique(['lpb_page_id', 'name'], 'uq_page_name');
                $table->unique(['lpb_page_id', 'property'], 'uq_page_property');
                $table->index(['lpb_page_id'], 'idx_metatags_page');

                $table->check("((name IS NOT NULL) <> (property IS NOT NULL))", 'chk_metatags_name_xor_property');
            });
        }
    }

    public function down(): void {
        Schema::dropIfExists('lpb_metatags');
    }
};
