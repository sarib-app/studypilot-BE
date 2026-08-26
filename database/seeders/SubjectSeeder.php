<?php

namespace Database\Seeders;

use App\Models\Subject;
use Illuminate\Database\Seeder;

class SubjectSeeder extends Seeder
{
    /**
     * Stands in for the admin content-authoring tool, which doesn't exist until Phase 4.
     */
    public function run(): void
    {
        $subjects = [
            ['name' => 'Calculus', 'color_key' => 'math'],
            ['name' => 'Biology', 'color_key' => 'bio'],
            ['name' => 'Physics', 'color_key' => 'math'],
            ['name' => 'History', 'color_key' => 'hist'],
            ['name' => 'English', 'color_key' => 'eng'],
        ];

        foreach ($subjects as $subject) {
            Subject::firstOrCreate(
                ['name' => $subject['name'], 'user_id' => null],
                ['color_key' => $subject['color_key']],
            );
        }
    }
}
