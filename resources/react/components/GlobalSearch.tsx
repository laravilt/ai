import { usePage } from '@inertiajs/react';
import { ArrowRight, Command, Database, Loader2, Search, Sparkles, X, type LucideIcon } from 'lucide-react';
import {
    useEffect,
    useImperativeHandle,
    useMemo,
    useRef,
    useState,
    type KeyboardEvent as ReactKeyboardEvent,
    type Ref,
} from 'react';
import { createPortal } from 'react-dom';

import { cn } from '@/lib/utils';
import { useLocalization } from '@laravilt/support/composables';
import { useLatest } from '@laravilt/support/composables/hooks';
import { resolveIcon } from '@laravilt/support/lib/icons';

export interface SearchResult {
    id: string | number;
    title: string;
    subtitle?: string;
    url: string;
}

export interface SearchGroup {
    resource: string;
    label: string;
    icon?: string;
    url: string;
    results: SearchResult[];
}

interface PanelSearchProps {
    hasGlobalSearch?: boolean;
    globalSearchEndpoint?: string;
    globalSearchConfig?: {
        enabled?: boolean;
        useAI?: boolean;
        debounce?: number;
    };
    hasAI?: boolean;
}

export interface GlobalSearchHandle {
    open: () => void;
    close: () => void;
}

export interface GlobalSearchProps {
    placeholder?: string;
    onSelect?: (result: SearchResult, group: SearchGroup) => void;
    onClose?: () => void;
    ref?: Ref<GlobalSearchHandle>;
}

// Map of resource icons with fallback (declared but unused, same as the Vue component)
const resourceIconMap: Record<string, string> = {
    users: 'Users',
    products: 'Package',
    settings: 'Settings',
    documents: 'FileText',
    files: 'FileText',
};

// Get icon component for a group
function getIconComponent(iconName?: string): LucideIcon {
    if (!iconName) return Database;

    return resolveIcon(iconName) ?? Database;
}

// Get icon color class for a group (for variety) — unused, same as the Vue component
function getIconColorClass(index: number): string {
    const colors = [
        'bg-blue-500/10 text-blue-500 dark:bg-blue-500/20 dark:text-blue-400',
        'bg-purple-500/10 text-purple-500 dark:bg-purple-500/20 dark:text-purple-400',
        'bg-green-500/10 text-green-500 dark:bg-green-500/20 dark:text-green-400',
        'bg-orange-500/10 text-orange-500 dark:bg-orange-500/20 dark:text-orange-400',
        'bg-pink-500/10 text-pink-500 dark:bg-pink-500/20 dark:text-pink-400',
        'bg-cyan-500/10 text-cyan-500 dark:bg-cyan-500/20 dark:text-cyan-400',
    ];

    return colors[index % colors.length];
}

export { resourceIconMap, getIconColorClass };

export default function GlobalSearch({ placeholder = undefined, onSelect, onClose, ref }: GlobalSearchProps) {
    const { trans } = useLocalization();

    const page = usePage();
    const panel = (page.props as any)?.panel as PanelSearchProps | undefined;

    const [isOpen, setIsOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [loading, setLoading] = useState(false);
    const [results, setResults] = useState<SearchGroup[]>([]);
    const [selectedIndex, setSelectedIndex] = useState(0);
    const inputRef = useRef<HTMLInputElement | null>(null);
    const [useAI, setUseAIState] = useState(false);
    const useAIRef = useRef(false);

    const setUseAI = (value: boolean) => {
        useAIRef.current = value;
        setUseAIState(value);
    };

    // Initialize AI mode from config
    useEffect(() => {
        const initial = panel?.globalSearchConfig?.useAI ?? false;
        useAIRef.current = initial;
        setUseAIState(initial);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const hasGlobalSearch = panel?.hasGlobalSearch ?? true;
    const hasAIConfig = panel?.hasAI ?? false;
    const endpoint = panel?.globalSearchEndpoint ?? '/global-search';
    const debounceMs = panel?.globalSearchConfig?.debounce ?? 300;

    const latest = useLatest({ endpoint, debounceMs });

    // Translation helper with proper keys from laravilt-ai::ai.search.*
    const t = {
        placeholder: placeholder ?? trans('laravilt-ai::ai.search.placeholder'),
        ai_powered: trans('laravilt-ai::ai.search.ai_powered'),
        no_results: trans('laravilt-ai::ai.search.no_results'),
        no_results_for: trans('laravilt-ai::ai.search.no_results_for'),
        try_different: trans('laravilt-ai::ai.search.try_different'),
        start_typing: trans('laravilt-ai::ai.search.start_typing'),
        results_count: trans('laravilt-ai::ai.search.results_count'),
        type_to_search: trans('laravilt-ai::ai.search.type_to_search'),
        to_navigate: trans('laravilt-ai::ai.search.to_navigate'),
        to_select: trans('laravilt-ai::ai.search.to_select'),
        to_close: trans('laravilt-ai::ai.search.to_close'),
        toggle_ai: trans('laravilt-ai::ai.search.toggle_ai'),
        ai_enabled: trans('laravilt-ai::ai.search.ai_enabled'),
        ai_disabled: trans('laravilt-ai::ai.search.ai_disabled'),
    };

    // Flatten results for keyboard navigation
    const flatResults = useMemo(() => {
        const flat: { result: SearchResult; group: SearchGroup; index: number }[] = [];
        let index = 0;
        for (const group of results) {
            for (const result of group.results) {
                flat.push({ result, group, index });
                index++;
            }
        }
        return flat;
    }, [results]);

    const totalResults = flatResults.length;

    // Id of the most recent search, so an older in-flight response cannot overwrite newer results.
    const latestSearch = useRef(0);

    async function performSearch(searchQuery: string) {
        const searchId = ++latestSearch.current;
        const isLatest = () => searchId === latestSearch.current;

        setLoading(true);
        setSelectedIndex(0);

        try {
            const response = await fetch(
                `${latest.current.endpoint}?query=${encodeURIComponent(searchQuery)}&useAI=${useAIRef.current}`,
            );
            const data = await response.json();
            if (isLatest()) {
                setResults(data.results || []);
            }
        } catch (error) {
            console.error('Search error:', error);
            if (isLatest()) {
                setResults([]);
            }
        } finally {
            if (isLatest()) {
                setLoading(false);
            }
        }
    }

    // Search debounce (Vue `watch(query)` — only reacts to changes, not the initial value)
    const searchTimeout = useRef<ReturnType<typeof setTimeout> | null>(null);
    const previousQuery = useRef(query);

    useEffect(() => {
        if (previousQuery.current === query) {
            return;
        }
        previousQuery.current = query;

        if (searchTimeout.current) {
            clearTimeout(searchTimeout.current);
        }

        if (!query.trim()) {
            // Invalidate any in-flight search so it cannot repopulate the cleared results.
            latestSearch.current++;
            setResults([]);
            setLoading(false);
            return;
        }

        const newQuery = query;
        searchTimeout.current = setTimeout(async () => {
            await performSearch(newQuery);
        }, latest.current.debounceMs);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [query]);

    function toggleAI() {
        setUseAI(!useAIRef.current);
        // Re-search if there's a query
        if (query.trim()) {
            performSearch(query);
        }
    }

    function open() {
        setIsOpen(true);
        setTimeout(() => {
            inputRef.current?.focus();
        }, 100);
    }

    function close() {
        setIsOpen(false);
        setQuery('');
        setResults([]);
        setSelectedIndex(0);
        onClose?.();
    }

    function selectResult(result: SearchResult, group: SearchGroup) {
        onSelect?.(result, group);
        if (result.url) {
            window.location.href = result.url;
        }
        close();
    }

    function handleKeydown(event: ReactKeyboardEvent<HTMLInputElement>) {
        if (!isOpen) return;

        switch (event.key) {
            case 'ArrowDown':
                event.preventDefault();
                setSelectedIndex(Math.min(selectedIndex + 1, totalResults - 1));
                break;
            case 'ArrowUp':
                event.preventDefault();
                setSelectedIndex(Math.max(selectedIndex - 1, 0));
                break;
            case 'Enter': {
                event.preventDefault();
                const selected = flatResults[selectedIndex];
                if (selected) {
                    selectResult(selected.result, selected.group);
                }
                break;
            }
            case 'Escape':
                event.preventDefault();
                close();
                break;
        }
    }

    // Global keyboard shortcut (Cmd/Ctrl + K) and ESC
    const handleGlobalKeydown = useLatest((event: KeyboardEvent) => {
        // Handle Cmd/Ctrl + K
        if ((event.metaKey || event.ctrlKey) && event.key === 'k') {
            event.preventDefault();
            if (isOpen) {
                close();
            } else {
                open();
            }
            return;
        }

        // Handle ESC globally (even when input is not focused)
        if (event.key === 'Escape' && isOpen) {
            event.preventDefault();
            event.stopPropagation();
            close();
        }
    });

    useEffect(() => {
        const listener = (event: KeyboardEvent) => handleGlobalKeydown.current(event);
        document.addEventListener('keydown', listener, true);

        return () => {
            document.removeEventListener('keydown', listener, true);
        };
    }, [handleGlobalKeydown]);

    useImperativeHandle(ref, () => ({ open, close }));

    return (
        <>
            {/* Trigger Button - shadcn style */}
            {hasGlobalSearch && (
                <button
                    type="button"
                    className="inline-flex items-center justify-center gap-2 rounded-lg border border-input bg-background px-3 py-1.5 text-sm text-muted-foreground shadow-sm transition hover:bg-accent hover:text-accent-foreground"
                    title={t.placeholder}
                    onClick={open}
                >
                    <Search className="h-4 w-4" />
                    <kbd className="pointer-events-none hidden h-5 select-none items-center gap-0.5 rounded border border-input bg-muted px-1.5 font-mono text-[10px] font-medium sm:flex">
                        <Command className="h-2.5 w-2.5" />K
                    </kbd>
                </button>
            )}

            {/* Spotlight Modal - shadcn style */}
            {isOpen &&
                typeof document !== 'undefined' &&
                createPortal(
                    <div className="fixed inset-0 z-50 overflow-y-auto p-4 pt-[25vh] sm:p-6 sm:pt-[20vh] animate-in fade-in-0 duration-200 ease-out">
                        {/* Backdrop - click to close */}
                        <div className="fixed inset-0 bg-black/50 backdrop-blur-sm transition-opacity" onClick={close} />

                        {/* Modal */}
                        <div className="relative mx-auto max-w-2xl transform overflow-hidden rounded-xl bg-popover text-popover-foreground shadow-2xl ring-1 ring-border transition-all animate-in fade-in-0 zoom-in-95 slide-in-from-bottom-4 duration-200 ease-out">
                            {/* Search Input - shadcn style */}
                            <div className="relative flex items-center px-4 py-3">
                                <div className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-muted">
                                    <Search className="h-5 w-5 text-muted-foreground" />
                                </div>
                                <input
                                    ref={inputRef}
                                    value={query}
                                    type="text"
                                    className="h-12 flex-1 border-0 bg-transparent px-4 text-lg text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-0"
                                    placeholder={t.placeholder}
                                    onChange={(event) => setQuery(event.target.value)}
                                    onKeyDown={handleKeydown}
                                />
                                <div className="flex items-center gap-2">
                                    {/* AI Toggle Button */}
                                    {hasAIConfig && (
                                        <button
                                            type="button"
                                            className={cn(
                                                'flex h-9 w-9 items-center justify-center rounded-lg transition-all duration-200',
                                                useAI
                                                    ? 'bg-primary text-primary-foreground shadow-sm'
                                                    : 'bg-muted text-muted-foreground hover:bg-accent hover:text-accent-foreground',
                                            )}
                                            title={useAI ? t.ai_enabled : t.ai_disabled}
                                            onClick={toggleAI}
                                        >
                                            <Sparkles className="h-4 w-4" />
                                        </button>
                                    )}
                                    {loading ? (
                                        <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
                                    ) : query ? (
                                        <button
                                            type="button"
                                            className="flex h-8 w-8 items-center justify-center rounded-lg bg-muted text-muted-foreground transition hover:bg-accent hover:text-accent-foreground"
                                            onClick={() => setQuery('')}
                                        >
                                            <X className="h-4 w-4" />
                                        </button>
                                    ) : null}
                                </div>
                            </div>

                            {/* AI Badge */}
                            {useAI && hasAIConfig && (
                                <div className="mx-4 mb-3 inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-3 py-1 text-xs font-medium text-primary">
                                    <Sparkles className="h-3 w-3" />
                                    <span>{t.ai_powered}</span>
                                </div>
                            )}

                            {/* Divider */}
                            {(results.length > 0 || query) && <div className="mx-4 h-px bg-border" />}

                            {/* Results - shadcn style */}
                            {results.length > 0 ? (
                                <div className="max-h-[50vh] overflow-y-auto overscroll-contain px-3 py-3">
                                    {results.map((group) => {
                                        const GroupIcon = getIconComponent(group.icon);

                                        return (
                                            <div key={group.resource} className="mb-4 last:mb-0">
                                                {/* Group Header */}
                                                <h3 className="mb-2 flex items-center gap-2 px-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                                    <GroupIcon className="h-3.5 w-3.5" />
                                                    {group.label}
                                                </h3>

                                                {/* Results List */}
                                                <ul className="space-y-1">
                                                    {group.results.map((result) => {
                                                        const flatIndex = flatResults.findIndex((f) => f.result === result);
                                                        const isSelected = flatIndex === selectedIndex;

                                                        return (
                                                            <li key={result.id}>
                                                                <button
                                                                    type="button"
                                                                    className={cn(
                                                                        'group flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left transition-all duration-150',
                                                                        isSelected
                                                                            ? 'bg-primary text-primary-foreground'
                                                                            : 'text-foreground hover:bg-accent',
                                                                    )}
                                                                    onClick={() => selectResult(result, group)}
                                                                    onMouseEnter={() => setSelectedIndex(flatIndex)}
                                                                >
                                                                    {/* Icon with color background */}
                                                                    <div
                                                                        className={cn(
                                                                            'flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg transition-colors',
                                                                            isSelected ? 'bg-primary-foreground/20' : 'bg-muted',
                                                                        )}
                                                                    >
                                                                        <GroupIcon
                                                                            className={cn(
                                                                                'h-5 w-5',
                                                                                isSelected
                                                                                    ? 'text-primary-foreground'
                                                                                    : 'text-muted-foreground',
                                                                            )}
                                                                        />
                                                                    </div>

                                                                    {/* Content */}
                                                                    <div className="flex-1 min-w-0">
                                                                        <div className="truncate font-medium">{result.title}</div>
                                                                        {result.subtitle && (
                                                                            <div
                                                                                className={cn(
                                                                                    'truncate text-sm',
                                                                                    isSelected
                                                                                        ? 'text-primary-foreground/70'
                                                                                        : 'text-muted-foreground',
                                                                                )}
                                                                            >
                                                                                {result.subtitle}
                                                                            </div>
                                                                        )}
                                                                    </div>

                                                                    {/* Arrow indicator */}
                                                                    <div
                                                                        className={cn(
                                                                            'flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-md transition-all',
                                                                            isSelected
                                                                                ? 'bg-primary-foreground/20 text-primary-foreground'
                                                                                : 'bg-muted text-muted-foreground opacity-0 group-hover:opacity-100',
                                                                        )}
                                                                    >
                                                                        <ArrowRight className="h-4 w-4" />
                                                                    </div>
                                                                </button>
                                                            </li>
                                                        );
                                                    })}
                                                </ul>
                                            </div>
                                        );
                                    })}
                                </div>
                            ) : query && !loading ? (
                                /* Empty State - shadcn style */
                                <div className="px-6 py-14 text-center">
                                    <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-xl bg-muted">
                                        <Search className="h-8 w-8 text-muted-foreground/50" />
                                    </div>
                                    <p className="text-lg font-medium text-foreground">{t.no_results}</p>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {trans('laravilt-ai::ai.search.no_results_for', { query })}
                                    </p>
                                </div>
                            ) : !query ? (
                                /* Initial State - shadcn style */
                                <div className="px-6 py-14 text-center">
                                    <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-xl bg-muted">
                                        <Search className="h-8 w-8 text-muted-foreground/50" />
                                    </div>
                                    <p className="text-lg font-medium text-foreground">{t.start_typing}</p>
                                    <p className="mt-1 text-sm text-muted-foreground">{t.type_to_search}</p>
                                </div>
                            ) : null}

                            {/* Footer - shadcn style */}
                            <div className="flex items-center justify-between border-t border-border bg-muted/50 px-4 py-2.5">
                                <span className="text-xs text-muted-foreground">
                                    {totalResults > 0
                                        ? trans('laravilt-ai::ai.search.results_count', { count: totalResults })
                                        : t.type_to_search}
                                </span>
                                {/* Keyboard hints - hidden on mobile */}
                                <div className="hidden items-center gap-3 text-xs text-muted-foreground sm:flex">
                                    <div className="flex items-center gap-1">
                                        <kbd className="flex h-5 items-center justify-center rounded border border-input bg-background px-1.5 font-mono text-[10px] font-medium">
                                            ↑
                                        </kbd>
                                        <kbd className="flex h-5 items-center justify-center rounded border border-input bg-background px-1.5 font-mono text-[10px] font-medium">
                                            ↓
                                        </kbd>
                                        <span className="ms-1">{t.to_navigate}</span>
                                    </div>
                                    <div className="flex items-center gap-1">
                                        <kbd className="flex h-5 items-center justify-center rounded border border-input bg-background px-1.5 font-mono text-[10px] font-medium">
                                            ↵
                                        </kbd>
                                        <span className="ms-1">{t.to_select}</span>
                                    </div>
                                    <div className="flex items-center gap-1">
                                        <kbd className="flex h-5 items-center justify-center rounded border border-input bg-background px-2 font-mono text-[10px] font-medium">
                                            esc
                                        </kbd>
                                        <span className="ms-1">{t.to_close}</span>
                                    </div>
                                </div>
                                {/* Mobile close button */}
                                <button
                                    type="button"
                                    className="flex items-center gap-1.5 rounded-md px-2 py-1 text-xs text-muted-foreground hover:bg-accent hover:text-accent-foreground sm:hidden"
                                    onClick={close}
                                >
                                    <X className="h-3.5 w-3.5" />
                                    <span>{t.to_close}</span>
                                </button>
                            </div>
                        </div>
                    </div>,
                    document.body,
                )}
        </>
    );
}
