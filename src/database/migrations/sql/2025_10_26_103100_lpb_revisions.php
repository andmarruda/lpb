<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // Append-only: no updated_at, no foreign key, so history outlives the entity
        Schema::create('lpb_revisions', function (Blueprint $table) {
            $table->id();
            $table->string('revisionable_type');
            $table->string('revisionable_id');
            $table->json('payload');
            $table->string('status');
            $table->string('created_by')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['revisionable_type', 'revisionable_id', 'created_at'], 'idx_revisions_revisionable');
        });
    }

    public function down(): void {
        Schema::dropIfExists('lpb_revisions');
    }
};
