<?php

declare(strict_types=1);

namespace Laravilt\AI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AISession extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'ai_sessions';

    protected $fillable = [
        'id',
        'user_id',
        'title',
        'provider',
        'model',
        'messages',
        'metadata',
    ];

    protected $casts = [
        'messages' => 'array',
        'metadata' => 'array',
    ];

    /**
     * @return BelongsTo<Model, self>
     */
    public function user(): BelongsTo
    {
        $userModel = config('auth.providers.users.model', 'App\\Models\\User');

        return $this->belongsTo($userModel, 'user_id');
    }

    public function addMessage(string $role, string $content): static
    {
        $messages = $this->messages ?? [];
        $messages[] = [
            'role' => $role,
            'content' => $content,
        ];
        $this->messages = $messages;
        $this->save();

        return $this;
    }

    public function clearMessages(): static
    {
        $this->messages = [];
        $this->save();

        return $this;
    }

    public function getLastMessage(): ?array
    {
        $messages = $this->messages ?? [];

        return end($messages) ?: null;
    }

    public function getMessageCount(): int
    {
        return count($this->messages ?? []);
    }
}
