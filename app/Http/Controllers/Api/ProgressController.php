<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StudySession;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProgressController extends Controller
{
    private const DAILY_BREAKDOWN_DAYS = 14;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        StudySession::flipOverdue($user->id);

        // offset=0 is the 14 days ending today; offset=1 the 14 days before that, and so on —
        // lets the Progress screen page backward through history. The headline stats (streak,
        // this week's hours/goal) always reflect right now regardless of which window is being
        // browsed, same as Home.
        $offset = max(0, (int) $request->integer('offset'));
        $windowEnd = Carbon::today()->subDays($offset * self::DAILY_BREAKDOWN_DAYS);

        return response()->json([
            'streak_days' => StudySession::currentStreakFor($user),
            ...StudySession::weeklyStatsFor($user),
            'days' => StudySession::dailyMinutesFor($user, self::DAILY_BREAKDOWN_DAYS, $windowEnd),
        ]);
    }
}
