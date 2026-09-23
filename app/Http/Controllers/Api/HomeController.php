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

        // Nearest missed session — surfaced on Home so Pilot can suggest rescheduling it instead
        // of it just sitting passively in the Plan list.
        $missedSession = StudySession::where('user_id', $user->id)
            ->where('status', 'missed')
            ->orderByDesc('scheduled_at')
            ->with('subject')
            ->first();

        [$weekStart, $weekEnd] = StudySession::weekRange();
        $weeklyMinutes = StudySession::where('user_id', $user->id)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$weekStart, $weekEnd])
            ->sum('actual_minutes');

        $todayMinutes = StudySession::where('user_id', $user->id)
            ->where('status', 'completed')
            ->whereDate('completed_at', now())
            ->sum('actual_minutes');

        // Total planned today (any status) — feeds Pilot's Home briefing ("you've got 50 min
        // planned today"), distinct from today_minutes_studied which is only what's actually done.
        $todayPlannedMinutes = StudySession::where('user_id', $user->id)
            ->whereDate('scheduled_at', now())
            ->whereIn('status', ['planned', 'missed', 'in_progress', 'completed'])
            ->sum('duration_minutes');

        $weeklyGoalHours = $user->weekly_study_goal_hours ?? 0;
        $weeklyHoursStudied = round($weeklyMinutes / 60, 1);

        return response()->json([
            'next_session' => $nextSession,
            'missed_session' => $missedSession,
            'today_minutes_studied' => (int) $todayMinutes,
            'today_planned_minutes' => (int) $todayPlannedMinutes,
            'streak_days' => StudySession::currentStreakFor($user),
            'weekly_goal_hours' => $weeklyGoalHours,
            'weekly_hours_studied' => $weeklyHoursStudied,
            'weekly_goal_progress' => $weeklyGoalHours > 0
                ? min(1, round($weeklyHoursStudied / $weeklyGoalHours, 2))
                : 0,
        ]);
    }
}
