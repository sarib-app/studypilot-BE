<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Rules\MinimumAge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json($request->user()->load('subjects'));
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        // Google/Apple sign-up never collects DOB (those providers don't share it), so
        // Profile Setup asks for it then instead. Email/password users already have one.
        $dobRules = $user->dob ? ['nullable'] : ['required'];

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'grade_year' => ['required', 'string', 'max:50'],
            'curriculum' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'dob' => ['bail', ...$dobRules, 'date', 'before:today', new MinimumAge(13)],
        ]);

        if ($validator->fails()) {
            // Same 13+ rule as signup — an OAuth account can't stay under-13 just because
            // the age couldn't be checked until now.
            if (in_array('under_13_blocked', $validator->errors()->get('dob'), true)) {
                $user->tokens()->delete();
                $user->delete();
            }

            throw new ValidationException($validator);
        }

        $user->update($validator->validated());

        return response()->json($user);
    }

    public function updateSubjects(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subjects' => ['required', 'array', 'min:1'],
            'subjects.*' => ['required', 'string', 'max:255'],
        ]);

        $user = $request->user();

        $subjectIds = collect($validated['subjects'])->map(function (string $name) use ($user) {
            $global = Subject::whereNull('user_id')->where('name', $name)->first();
            if ($global) {
                return $global->id;
            }

            // Not on the admin-managed list — stored as a custom subject scoped to this user only.
            $custom = Subject::firstOrCreate(
                ['user_id' => $user->id, 'name' => $name],
            );

            return $custom->id;
        });

        $user->subjects()->sync($subjectIds);

        return response()->json($user->load('subjects'));
    }

    public function updateGoal(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'goal_type' => ['required', 'string', 'in:Stay consistent,Prepare for tests,Manage my time,Improve difficult subjects'],
            'weekly_study_goal_hours' => ['required', 'integer', 'min:1', 'max:80'],
        ]);

        $user = $request->user();
        $user->update($validated);

        return response()->json($user);
    }

    public function updateReminderSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reminder_lead_minutes' => ['required', 'integer', 'in:0,10,15,30,60'],
            'nudge_if_not_begun' => ['required', 'boolean'],
        ]);

        $user = $request->user();
        $user->update($validated);

        return response()->json($user);
    }
}
