<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StudySession;
use App\Models\Subject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SessionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        StudySession::flipOverdue($user->id);

        $query = StudySession::where('user_id', $user->id)->with('subject');

        if ($request->string('view')->value() === 'week') {
            $date = $request->filled('date') ? $request->date('date') : now();
            [$start, $end] = StudySession::weekRange($date);
            $query->whereBetween('scheduled_at', [$start, $end]);
        } else {
            $date = $request->filled('date') ? $request->date('date') : now();
            $query->whereDate('scheduled_at', $date);
        }

        return response()->json($query->orderBy('scheduled_at')->get());
    }

    public function show(Request $request, StudySession $session): JsonResponse
    {
        $this->authorizeOwner($request, $session);

        return response()->json($session->load('subject'));
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $this->validateSession($request);
        $this->authorizeSubject($user->id, $validated['subject_id']);

        $session = StudySession::create([...$validated, 'user_id' => $user->id]);

        return response()->json($session->load('subject'), 201);
    }

    public function update(Request $request, StudySession $session): JsonResponse
    {
        $this->authorizeOwner($request, $session);

        $validated = $this->validateSession($request);
        $this->authorizeSubject($request->user()->id, $validated['subject_id']);

        // Editing a missed session (rescheduling it) means trying again — it shouldn't stay
        // stuck as "missed" just because its scheduled_at moved into the future.
        if ($session->status === 'missed') {
            $validated['status'] = 'planned';
        }

        $session->update($validated);

        return response()->json($session->load('subject'));
    }

    public function destroy(Request $request, StudySession $session): JsonResponse
    {
        $this->authorizeOwner($request, $session);
        $session->delete();

        return response()->json(['message' => 'Session deleted.']);
    }

    public function start(Request $request, StudySession $session): JsonResponse
    {
        $this->authorizeOwner($request, $session);

        if (! in_array($session->status, ['planned', 'missed'], true)) {
            throw ValidationException::withMessages([
                'status' => ["Can't start a session that is already {$session->status}."],
            ]);
        }

        $session->update(['status' => 'in_progress', 'started_at' => now()]);

        return response()->json($session->load('subject'));
    }

    public function complete(Request $request, StudySession $session): JsonResponse
    {
        $this->authorizeOwner($request, $session);

        // "missed" is allowed too — that's how the Plan screen's "Mark complete" retroactively
        // logs a session that was never live-started (actual_minutes then just mirrors the plan).
        if (! in_array($session->status, ['in_progress', 'missed'], true)) {
            throw ValidationException::withMessages([
                'status' => ["Can't complete a session that is {$session->status}."],
            ]);
        }

        $validated = $request->validate([
            // 0 is allowed — that's what a retroactive "Mark complete" on a missed session sends,
            // since it never actually ran the timer and shouldn't be credited real study time.
            'actual_minutes' => ['required', 'integer', 'min:0', 'max:600'],
            'reflection' => ['nullable', 'string', 'max:2000'],
            'local_date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $session->update([
            'status' => 'completed',
            'completed_at' => now(),
            'actual_minutes' => $validated['actual_minutes'],
            'reflection' => $validated['reflection'] ?? null,
            'local_date' => $validated['local_date'] ?? now()->toDateString(),
        ]);

        // 1 XP per minute studied — simple and transparent; not part of the original MVP spec,
        // added per Stefan's Phase 3 review feedback. Level = floor(xp / 1000) + 1.
        $request->user()->increment('xp', $validated['actual_minutes']);

        return response()->json($session->load('subject'));
    }

    /** Attaches a reflection after the fact — used by the completion screen, which completes the
     * session immediately on load (so streak/goal feedback there is real, not projected) and only
     * attaches the optional feeling chip once the user picks one and taps Done. */
    public function reflect(Request $request, StudySession $session): JsonResponse
    {
        $this->authorizeOwner($request, $session);

        $validated = $request->validate([
            'reflection' => ['nullable', 'string', 'max:2000'],
        ]);

        $session->update(['reflection' => $validated['reflection'] ?? null]);

        return response()->json($session->load('subject'));
    }

    /** Duplicates every session from last week onto the corresponding day this week, as fresh planned sessions. */
    public function copyLastWeek(Request $request): JsonResponse
    {
        $user = $request->user();
        [$lastStart, $lastEnd] = StudySession::weekRange(now()->subWeek());

        $lastWeekSessions = StudySession::where('user_id', $user->id)
            ->whereBetween('scheduled_at', [$lastStart, $lastEnd])
            ->get();

        $copies = $lastWeekSessions->map(fn (StudySession $session) => [
            'user_id' => $user->id,
            'subject_id' => $session->subject_id,
            'topic' => $session->topic,
            'scheduled_at' => $session->scheduled_at->copy()->addWeek(),
            'duration_minutes' => $session->duration_minutes,
            'status' => 'planned',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($copies->isNotEmpty()) {
            StudySession::insert($copies->all());
        }

        return response()->json(['copied' => $copies->count()]);
    }

    private function validateSession(Request $request): array
    {
        return $request->validate([
            'subject_id' => ['required', 'integer'],
            'topic' => ['nullable', 'string', 'max:255'],
            'scheduled_at' => ['required', 'date'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:240'],
        ]);
    }

    /** A subject_id must be a global subject or one this user owns — never someone else's custom subject. */
    private function authorizeSubject(int $userId, int $subjectId): void
    {
        $subject = Subject::find($subjectId);

        if (! $subject || ($subject->user_id !== null && $subject->user_id !== $userId)) {
            throw ValidationException::withMessages([
                'subject_id' => ['This subject is not available to you.'],
            ]);
        }
    }

    private function authorizeOwner(Request $request, StudySession $session): void
    {
        abort_if($session->user_id !== $request->user()->id, 404);
    }
}
