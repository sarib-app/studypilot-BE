<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StudySession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        StudySession::flipOverdue($user->id);

        $nextSession = StudySession::where('user_id', $user->id)
            ->where('status', 'planned')
            ->where('scheduled_at', '>=', now())
            ->orderBy('scheduled_at')
            ->with('subject')
            ->first();

        [$weekStart, $weekEnd] = StudySession::weekRange();
        $weeklyMinutes = StudySession::where('user_id', $user->id)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$weekStart, $weekEnd])
            ->sum('actual_minutes');

        $weeklyGoalHours = $user->weekly_study_goal_hours ?? 0;
        $weeklyHoursStudied = round($weeklyMinutes / 60, 1);

        return response()->json([
            'next_session' => $nextSession,
            'streak_days' => StudySession::currentStreakFor($user),
            'weekly_goal_hours' => $weeklyGoalHours,
            'weekly_hours_studied' => $weeklyHoursStudied,
            'weekly_goal_progress' => $weeklyGoalHours > 0
                ? min(1, round($weeklyHoursStudied / $weeklyGoalHours, 2))
                : 0,
        ]);
    }
}
