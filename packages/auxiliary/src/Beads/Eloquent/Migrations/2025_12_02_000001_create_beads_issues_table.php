<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('beads_issues', function (Blueprint $table) {
            $table->id();
            $table->string('issue_id')->unique()->index();
            $table->string('title', 500);
            $table->text('description');
            $table->string('status')->index();
            $table->tinyInteger('priority')->default(2)->index();
            $table->string('issue_type')->index();

            // Optional fields
            $table->string('assignee')->nullable()->index();
            $table->text('design')->nullable();
            $table->text('acceptance_criteria')->nullable();
            $table->text('notes')->nullable();
            $table->integer('estimated_minutes')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('close_reason')->nullable();
            $table->string('external_ref')->nullable()->index();
            $table->json('labels')->nullable();

            // Compaction fields
            $table->tinyInteger('compaction_level')->nullable();
            $table->timestamp('compacted_at')->nullable();
            $table->string('compacted_at_commit')->nullable();
            $table->integer('original_size')->nullable();

            $table->timestamps();

            // Indexes for common queries
            $table->index(['status', 'priority']);
            $table->index(['issue_type', 'status']);
            $table->index(['assignee', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('beads_issues');
    }
};
