<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'subject_id', 'topic', 'scheduled_at', 'duration_minutes', 'status',
    'started_at', 'completed_at', 'actual_minutes', 'local_date', 'reflection',
])]
class StudySession extends Model
{
    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * A "planned" session past its scheduled end time is stale. Flipping it here — called at
     * the top of both SessionController@index and HomeController@index — keeps "missed" status
     * consistent everywhere instead of only wherever it happens to be checked first.
     */
    public static function flipOverdue(int $userId): void
    {
        $overdueIds = static::where('user_id', $userId)
            ->where('status', 'planned')
            ->where('scheduled_at', '<', now())
            ->get(['id', 'scheduled_at', 'duration_minutes'])
            ->filter(fn (self $session) => $session->scheduled_at->addMinutes($session->duration_minutes)->isPast())
            ->pluck('id');

        if ($overdueIds->isNotEmpty()) {
            static::whereIn('id', $overdueIds)->update(['status' => 'missed']);
        }
    }

    /**
     * Consecutive-day streak, cumulative per day (two 10-minute sessions in one day still count),
     * bucketed by the device-reported local_date rather than completed_at — the server has no
     * reliable user timezone to bucket by otherwise.
     */
    public static function currentStreakFor(User $user): int
    {
        $qualifyingDays = static::where('user_id', $user->id)
            ->where('status', 'completed')
            ->whereNotNull('local_date')
            ->selectRaw('local_date')
            ->groupBy('local_date')
            ->havingRaw('SUM(actual_minutes) >= 15')
            ->pluck('local_date')
            ->flip();

        $streak = 0;
        $cursor = Carbon::today();

        if (! $qualifyingDays->has($cursor->toDateString())) {
            $cursor->subDay();
        }

        while ($qualifyingDays->has($cursor->toDateString())) {
            $streak++;
            $cursor->subDay();
        }

        return $streak;
    }

    /** Monday-start week boundary, shared so the ?view=week filter and Home's weekly stats can't drift apart. */
    public static function weekRange(?Carbon $date = null): array
    {
        $date ??= Carbon::now();

        return [
            $date->copy()->startOfWeek(Carbon::MONDAY),
            $date->copy()->endOfWeek(Carbon::SUNDAY),
        ];
    }

    /** Shared by HomeController and ProgressController so the two screens can never show
     * different numbers for the same week. */
    public static function weeklyStatsFor(User $user): array
    {
        [$weekStart, $weekEnd] = static::weekRange();
        $weeklyMinutes = static::where('user_id', $user->id)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$weekStart, $weekEnd])
            ->sum('actual_minutes');

        $weeklyGoalHours = $user->weekly_study_goal_hours ?? 0;
        $weeklyHoursStudied = round($weeklyMinutes / 60, 1);

        return [
            'weekly_goal_hours' => $weeklyGoalHours,
            'weekly_hours_studied' => $weeklyHoursStudied,
            'weekly_goal_progress' => $weeklyGoalHours > 0
                ? min(1, round($weeklyHoursStudied / $weeklyGoalHours, 2))
                : 0,
        ];
    }

    /** Per-day totals for the last $days days (today inclusive), bucketed by local_date exactly
     * like the streak calculation — so the daily breakdown and the streak count can never disagree
     * about which days qualified. */
    public static function dailyMinutesFor(User $user, int $days): array
    {
        $start = Carbon::today()->subDays($days - 1);

        $byDate = static::where('user_id', $user->id)
            ->where('status', 'completed')
            ->whereNotNull('local_date')
            ->where('local_date', '>=', $start->toDateString())
            ->selectRaw('local_date, SUM(actual_minutes) as minutes')
            ->groupBy('local_date')
            ->pluck('minutes', 'local_date');

        $result = [];
        for ($cursor = $start->copy(); $cursor->lte(Carbon::today()); $cursor->addDay()) {
            $minutes = (int) ($byDate[$cursor->toDateString()] ?? 0);
            $result[] = [
                'date' => $cursor->toDateString(),
                'minutes' => $minutes,
                'goal_met' => $minutes >= 15,
            ];
        }

        return $result;
    }
}
