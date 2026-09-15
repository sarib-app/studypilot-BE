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

        if ($session->status !== 'in_progress') {
            throw ValidationException::withMessages([
                'status' => ["Can't complete a session that is {$session->status}."],
            ]);
        }

        $validated = $request->validate([
            'actual_minutes' => ['required', 'integer', 'min:1', 'max:600'],
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

        return response()->json($session->load('subject'));
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
