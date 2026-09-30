<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StudySession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProgressController extends Controller
{
    private const DAILY_BREAKDOWN_DAYS = 14;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        StudySession::flipOverdue($user->id);

        return response()->json([
            'streak_days' => StudySession::currentStreakFor($user),
            ...StudySession::weeklyStatsFor($user),
            'days' => StudySession::dailyMinutesFor($user, self::DAILY_BREAKDOWN_DAYS),
        ]);
    }
}
