<?php

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
        Schema::create('study_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->string('topic')->nullable();
            $table->dateTime('scheduled_at');
            $table->unsignedInteger('duration_minutes');
            $table->enum('status', ['planned', 'in_progress', 'completed', 'missed'])->default('planned');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->unsignedInteger('actual_minutes')->nullable();
            // Y-m-d as reported by the device at completion time — sidesteps server timezone
            // infra entirely for streak day-bucketing (the server has no user timezone to go on).
            $table->string('local_date', 10)->nullable();
            $table->text('reflection')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('study_sessions');
    }
};
