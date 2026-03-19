<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instructor_agent_sessions', function (Blueprint $table): void {
            $table->string('session_id')->primary();
            $table->string('parent_session_id')->nullable()->index();
            $table->string('status')->index();
            $table->unsignedInteger('version');
            $table->string('agent_name')->index();
            $table->string('agent_label');
            $table->longText('payload');
            $table->dateTimeTz('created_at');
            $table->dateTimeTz('updated_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instructor_agent_sessions');
    }
};
