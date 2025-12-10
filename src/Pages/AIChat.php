<?php

declare(strict_types=1);

namespace Laravilt\AI\Pages;

use Laravilt\Panel\Pages\Page;

class AIChat extends Page
{
    protected static ?string $title = null;

    protected static ?string $navigationIcon = 'Sparkles';

    protected static ?string $slug = 'ai';

    protected static string $view = 'laravilt/AIChat';

    /**
     * Don't show in sidebar navigation.
     */
    protected static bool $shouldRegisterNavigation = false;

    public static function getTitle(): string
    {
        return static::$title ?? __('laravilt-ai::ai.chat.title');
    }

    public static function getLabel(): string
    {
        return __('laravilt-ai::ai.chat.title');
    }

    /**
     * Get breadcrumbs for AI Chat page.
     */
    public function getBreadcrumbs(): array
    {
        return [
            [
                'label' => __('laravilt-panel::panel.navigation.dashboard'),
                'url' => $this->getPanel()->url('/'),
            ],
            [
                'label' => static::getTitle(),
                'url' => null,
            ],
        ];
    }

    /**
     * Get extra props for Inertia response.
     */
    protected function getInertiaProps(): array
    {
        $panel = $this->getPanel();

        return [
            'aiConfig' => $panel->getAIConfig(),
            'hasAI' => $panel->hasAIProviders(),
        ];
    }
}
