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
        Schema::table('users', function (Blueprint $table) {
            $table->date('dob')->nullable()->after('email');
            $table->string('grade_year')->nullable()->after('dob');
            $table->string('curriculum_region')->nullable()->after('grade_year');
            $table->string('goal_type')->nullable()->after('curriculum_region');
            $table->unsignedTinyInteger('weekly_study_goal_hours')->nullable()->after('goal_type');
            $table->string('google_id')->nullable()->unique()->after('weekly_study_goal_hours');
            $table->string('apple_id')->nullable()->unique()->after('google_id');
            // Always null in MVP — reserved for the future school/organization tier (no
            // organizations table yet, so no FK constraint until that phase is built).
            $table->unsignedBigInteger('organization_id')->nullable()->after('apple_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'dob',
                'grade_year',
                'curriculum_region',
                'goal_type',
                'weekly_study_goal_hours',
                'google_id',
                'apple_id',
                'organization_id',
            ]);
        });
    }
};
