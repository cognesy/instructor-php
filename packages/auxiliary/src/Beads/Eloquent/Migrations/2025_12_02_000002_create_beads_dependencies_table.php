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
        Schema::create('beads_dependencies', function (Blueprint $table) {
            $table->id();
            $table->string('issue_id')->index();
            $table->string('depends_on_id')->index();
            $table->string('type')->index();
            $table->string('created_by');
            $table->timestamps();

            // Foreign keys
            $table->foreign('issue_id')
                ->references('issue_id')
                ->on('beads_issues')
                ->onDelete('cascade');

            $table->foreign('depends_on_id')
                ->references('issue_id')
                ->on('beads_issues')
                ->onDelete('cascade');

            // Unique constraint to prevent duplicate dependencies
            $table->unique(['issue_id', 'depends_on_id', 'type']);

            // Index for querying dependencies by type
            $table->index(['issue_id', 'type']);
            $table->index(['depends_on_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('beads_dependencies');
    }
};
