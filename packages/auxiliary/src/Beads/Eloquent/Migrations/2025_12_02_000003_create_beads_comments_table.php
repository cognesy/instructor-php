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
        Schema::create('beads_comments', function (Blueprint $table) {
            $table->id();
            $table->string('issue_id')->index();
            $table->string('author')->index();
            $table->text('text');
            $table->timestamps();

            // Foreign key
            $table->foreign('issue_id')
                ->references('issue_id')
                ->on('beads_issues')
                ->onDelete('cascade');

            // Indexes for common queries
            $table->index(['issue_id', 'created_at']);
            $table->index(['author', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('beads_comments');
    }
};
