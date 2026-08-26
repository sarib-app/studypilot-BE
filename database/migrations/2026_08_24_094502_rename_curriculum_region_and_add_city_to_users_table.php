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
            // "curriculum_region" split into two clearer fields: the curriculum/exam board
            // (American, Cambridge, Oxford AQA, IB...) and city (launch market is US-only for now).
            $table->dropColumn('curriculum_region');
            $table->string('curriculum')->nullable()->after('grade_year');
            $table->string('city')->nullable()->after('curriculum');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['curriculum', 'city']);
            $table->string('curriculum_region')->nullable()->after('grade_year');
        });
    }
};
