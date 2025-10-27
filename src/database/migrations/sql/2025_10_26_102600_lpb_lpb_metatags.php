<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
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
        });

        // CHECK ((name IS NOT NULL) <> (property IS NOT NULL))
        DB::statement("ALTER TABLE lpb_metatags
            ADD CONSTRAINT chk_metatags_name_xor_property
            CHECK ((name IS NOT NULL) <> (property IS NOT NULL))");
    }

    public function down(): void {
        Schema::dropIfExists('lpb_metatags');
    }
};
