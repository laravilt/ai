<?php

declare(strict_types=1);

namespace Laravilt\AI\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Laravilt\AI\AIManager;
use Laravilt\AI\Models\AISession;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AIController extends Controller
{
    public function __construct(
        protected AIManager $aiManager
    ) {}

    public function config(): JsonResponse
    {
        return response()->json($this->aiManager->toArray());
    }

    public function chat(Request $request): JsonResponse
    {
        $request->validate([
            'messages' => 'required|array',
            'messages.*.role' => 'required|string|in:system,user,assistant',
            'messages.*.content' => 'required|string',
            'provider' => 'nullable|string',
            'model' => 'nullable|string',
            'session_id' => 'nullable|string',
        ]);

        $provider = $this->aiManager->provider($request->input('provider'));

        $response = $provider->chat(
            $request->input('messages'),
            [
                'model' => $request->input('model'),
            ]
        );

        // Save to session if provided
        if ($sessionId = $request->input('session_id')) {
            $this->saveToSession($sessionId, $request->input('messages'), $response);
        }

        return response()->json($response);
    }

    public function stream(Request $request): StreamedResponse
    {
        $request->validate([
            'messages' => 'required|array',
            'messages.*.role' => 'required|string|in:system,user,assistant',
            'messages.*.content' => 'required|string',
            'provider' => 'nullable|string',
            'model' => 'nullable|string',
        ]);

        $provider = $this->aiManager->provider($request->input('provider'));

        return response()->stream(function () use ($provider, $request) {
            $generator = $provider->streamChat(
                $request->input('messages'),
                [
                    'model' => $request->input('model'),
                ]
            );

            foreach ($generator as $chunk) {
                echo "data: ".json_encode(['content' => $chunk])."\n\n";
                ob_flush();
                flush();
            }

            echo "data: [DONE]\n\n";
            ob_flush();
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function sessions(Request $request): JsonResponse
    {
        $sessions = AISession::query()
            ->where('user_id', $request->user()?->id)
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get();

        return response()->json([
            'sessions' => $sessions,
        ]);
    }

    public function session(string $id): JsonResponse
    {
        $session = AISession::findOrFail($id);

        return response()->json([
            'session' => $session,
        ]);
    }

    public function createSession(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'nullable|string|max:255',
            'provider' => 'nullable|string',
            'model' => 'nullable|string',
        ]);

        $session = AISession::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $request->user()?->id,
            'title' => $request->input('title', 'New Chat'),
            'provider' => $request->input('provider'),
            'model' => $request->input('model'),
            'messages' => [],
            'metadata' => [],
        ]);

        return response()->json([
            'session' => $session,
        ]);
    }

    public function updateSession(Request $request, string $id): JsonResponse
    {
        $session = AISession::findOrFail($id);

        $request->validate([
            'title' => 'nullable|string|max:255',
            'messages' => 'nullable|array',
        ]);

        $session->update($request->only(['title', 'messages']));

        return response()->json([
            'session' => $session->fresh(),
        ]);
    }

    public function deleteSession(string $id): JsonResponse
    {
        $session = AISession::findOrFail($id);
        $session->delete();

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $response
     */
    protected function saveToSession(string $sessionId, array $messages, array $response): void
    {
        $session = AISession::find($sessionId);

        if (! $session) {
            return;
        }

        $currentMessages = $session->messages ?? [];

        // Add user message (last one in messages array)
        $lastUserMessage = collect($messages)->last(fn ($m) => $m['role'] === 'user');
        if ($lastUserMessage) {
            $currentMessages[] = $lastUserMessage;
        }

        // Add assistant response
        $currentMessages[] = [
            'role' => 'assistant',
            'content' => $response['content'],
        ];

        $session->update([
            'messages' => $currentMessages,
        ]);
    }
}
