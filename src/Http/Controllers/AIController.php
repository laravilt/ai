<?php

declare(strict_types=1);

namespace Laravilt\AI\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Laravilt\AI\AIManager;
use Laravilt\AI\Models\AISession;
use Laravilt\AI\Tools\ResourceQueryTool;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AIController extends Controller
{
    public function __construct(
        protected AIManager $aiManager
    ) {}

    /**
     * Build system message with resource context.
     *
     * @param  array<string>  $mentionedResources
     */
    protected function buildSystemMessage(array $mentionedResources = []): string
    {
        $resources = ResourceQueryTool::getAvailableResources();

        $resourceList = '';
        $focusedResources = '';

        foreach ($resources as $slug => $info) {
            $resourceList .= "- **{$info['label']}** (slug: `{$slug}`): {$info['count']} records. Fields: ".implode(', ', array_slice($info['fields'], 0, 10))."\n";

            // Add detailed info for mentioned resources
            if (in_array($slug, $mentionedResources)) {
                $focusedResources .= "\n### {$info['label']} (slug: `{$slug}`)\n";
                $focusedResources .= "- **Total records**: {$info['count']}\n";
                $focusedResources .= "- **All fields**: ".implode(', ', $info['fields'])."\n";
            }
        }

        $mentionedSection = '';
        if (! empty($mentionedResources) && ! empty($focusedResources)) {
            $mentionedSection = <<<MENTIONED

## IMPORTANT: The user has specifically mentioned these resources - focus your response on them:
{$focusedResources}
When answering, prioritize querying and providing information about these mentioned resources.
MENTIONED;
        }

        return <<<SYSTEM
You are an AI assistant for this admin panel. You have access to the following resources:

{$resourceList}
{$mentionedSection}

When the user asks about data or resources, you can use the available tools to query the database.
- Use `list_resources` to see all available resources
- Use `query_resource` to fetch, count, or find specific records

Always be helpful and provide accurate information based on the actual data.
SYSTEM;
    }

    public function config(): JsonResponse
    {
        return response()->json($this->aiManager->toArray());
    }

    /**
     * Get available resources for @ mentions.
     */
    public function resources(): JsonResponse
    {
        $availableResources = ResourceQueryTool::getAvailableResources();

        $resources = [];
        foreach ($availableResources as $slug => $info) {
            $resources[] = [
                'slug' => $slug,
                'label' => $info['label'],
                'singular' => $info['singular'],
                'count' => $info['count'],
                'fields' => $info['fields'],
            ];
        }

        return response()->json([
            'resources' => $resources,
        ]);
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
            'messages.*.role' => 'required|string|in:system,user,assistant,tool',
            'messages.*.content' => 'nullable|string',
            'provider' => 'nullable|string',
            'model' => 'nullable|string',
            'session_id' => 'nullable|string',
            'mentioned_resources' => 'nullable|array',
            'mentioned_resources.*' => 'string',
        ]);

        $providerName = $request->input('provider');
        $sessionId = $request->input('session_id');
        $userMessages = $request->input('messages');
        $model = $request->input('model');
        $mentionedResources = $request->input('mentioned_resources', []);

        // Add system message with resource context
        $systemMessage = $this->buildSystemMessage($mentionedResources);
        $messages = array_merge(
            [['role' => 'system', 'content' => $systemMessage]],
            $userMessages
        );

        // Get available tools
        $tools = ResourceQueryTool::getToolDefinitions();

        return response()->stream(function () use ($providerName, $messages, $sessionId, $model, $tools, $userMessages) {
            // Disable output buffering for real-time streaming
            while (ob_get_level() > 0) {
                ob_end_flush();
            }

            $fullContent = '';

            try {
                $provider = $this->aiManager->provider($providerName);

                // First, try with tools to see if AI wants to use them
                if (! empty($tools)) {
                    $toolResponse = $provider->chatWithTools($messages, $tools, ['model' => $model]);

                    // If AI wants to call tools
                    if (! empty($toolResponse['tool_calls'])) {
                        // Execute each tool call
                        $toolResults = [];
                        foreach ($toolResponse['tool_calls'] as $toolCall) {
                            $result = ResourceQueryTool::executeTool(
                                $toolCall['name'],
                                $toolCall['arguments']
                            );
                            $toolResults[] = [
                                'tool_call_id' => $toolCall['id'],
                                'name' => $toolCall['name'],
                                'result' => $result,
                            ];
                        }

                        // Add tool results to messages and get final response
                        $messagesWithTools = $messages;
                        $messagesWithTools[] = [
                            'role' => 'assistant',
                            'content' => $toolResponse['content'],
                            'tool_calls' => array_map(fn ($tc) => [
                                'id' => $tc['id'],
                                'type' => 'function',
                                'function' => [
                                    'name' => $tc['name'],
                                    'arguments' => json_encode($tc['arguments']),
                                ],
                            ], $toolResponse['tool_calls']),
                        ];

                        foreach ($toolResults as $result) {
                            $messagesWithTools[] = [
                                'role' => 'tool',
                                'tool_call_id' => $result['tool_call_id'],
                                'content' => json_encode($result['result']),
                            ];
                        }

                        // Stream the final response with tool results
                        $provider->streamChatRealtime(
                            $messagesWithTools,
                            function ($chunk) use (&$fullContent) {
                                $fullContent .= $chunk;
                                echo 'data: '.json_encode(['content' => $chunk])."\n\n";
                                flush();
                            },
                            ['model' => $model]
                        );
                    } elseif ($toolResponse['content']) {
                        // No tool calls, just return the content
                        $fullContent = $toolResponse['content'];
                        echo 'data: '.json_encode(['content' => $fullContent])."\n\n";
                        flush();
                    } else {
                        // Fallback to regular streaming
                        $provider->streamChatRealtime(
                            $messages,
                            function ($chunk) use (&$fullContent) {
                                $fullContent .= $chunk;
                                echo 'data: '.json_encode(['content' => $chunk])."\n\n";
                                flush();
                            },
                            ['model' => $model]
                        );
                    }
                } else {
                    // No tools available, use regular streaming
                    $provider->streamChatRealtime(
                        $messages,
                        function ($chunk) use (&$fullContent) {
                            $fullContent .= $chunk;
                            echo 'data: '.json_encode(['content' => $chunk])."\n\n";
                            flush();
                        },
                        ['model' => $model]
                    );
                }

                // Save to session after streaming completes
                if ($sessionId) {
                    $this->saveToSession($sessionId, $userMessages, ['content' => $fullContent]);
                }

                echo "data: [DONE]\n\n";
                flush();
            } catch (\Exception $e) {
                \Log::error('AI Stream error: '.$e->getMessage(), ['exception' => $e]);
                echo 'data: '.json_encode(['error' => $e->getMessage()])."\n\n";
                echo "data: [DONE]\n\n";
                flush();
            }
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
