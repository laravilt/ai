import { usePage } from '@inertiajs/react';
import {
    AtSign,
    Bot,
    Check,
    Copy,
    Database,
    Loader2,
    MessageSquare,
    MoreHorizontal,
    PanelLeft,
    PanelLeftClose,
    Plus,
    Send,
    Settings2,
    Sparkles,
    Trash2,
    User,
} from 'lucide-react';
import MarkdownIt from 'markdown-it';
import {
    useCallback,
    useEffect,
    useLayoutEffect,
    useMemo,
    useRef,
    useState,
    type ChangeEvent,
    type KeyboardEvent,
} from 'react';

import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { useLocalization } from '@laravilt/support/composables';
import { useLatest } from '@laravilt/support/composables/hooks';

// Initialize markdown parser
const md = new MarkdownIt({
    html: false,
    linkify: true,
    typographer: true,
    breaks: true,
});

export interface Message {
    role: 'system' | 'user' | 'assistant';
    content: string;
    timestamp?: number;
}

export interface Session {
    id: string;
    title: string;
    provider?: string;
    model?: string;
    messages: Message[];
    created_at: string;
    updated_at: string;
}

export interface Provider {
    name: string;
    label: string;
    models: Record<string, string>;
    defaultModel: string;
    configured: boolean;
}

export interface AIConfig {
    configured: boolean;
    default: string;
    providers: Record<string, Provider>;
}

export interface Resource {
    slug: string;
    label: string;
    singular: string;
    count: number;
    fields: string[];
}

export interface MentionedResource {
    slug: string;
    label: string;
}

export interface AIChatProps {
    initialSession?: Session;
    showSidebar?: boolean;
    endpoint?: string;
    onSessionChange?: (session: Session) => void;
}

export default function AIChat({
    initialSession,
    showSidebar = true,
    endpoint = '/laravilt-ai',
    onSessionChange,
}: AIChatProps) {
    const { trans } = useLocalization();
    const page = usePage();

    // State
    const [config, setConfig] = useState<AIConfig | null>(null);
    const [sessions, setSessions] = useState<Session[]>([]);
    const [currentSession, setCurrentSession] = useState<Session | null>(initialSession || null);
    const [messages, setMessages] = useState<Message[]>(() => initialSession?.messages ?? []);
    const [input, setInput] = useState('');
    const [loading, setLoading] = useState(false);
    const [streaming, setStreaming] = useState(false);
    const [configLoading, setConfigLoading] = useState(true);
    const [sidebarOpen, setSidebarOpen] = useState(true);

    const [selectedProvider, setSelectedProvider] = useState<string>('');
    const [selectedModel, setSelectedModel] = useState<string>('');

    // Resource mentions state
    const [resources, setResources] = useState<Resource[]>([]);
    const [showMentionDropdown, setShowMentionDropdown] = useState(false);
    const [mentionQuery, setMentionQuery] = useState('');
    const [mentionStartIndex, setMentionStartIndex] = useState(-1);
    const [selectedMentionIndex, setSelectedMentionIndex] = useState(0);
    const [mentionedResources, setMentionedResources] = useState<MentionedResource[]>([]);
    const mentionDropdownRef = useRef<HTMLDivElement | null>(null);

    const chatContainerRef = useRef<HTMLDivElement | null>(null);
    const inputRef = useRef<HTMLTextAreaElement | null>(null);
    const [copiedMessageIndex, setCopiedMessageIndex] = useState<number | null>(null);

    // The message list is mutated from async code (like the Vue ref), so keep a synchronous mirror.
    const messagesRef = useRef<Message[]>(messages);

    const commitMessages = useCallback((next: Message[]) => {
        messagesRef.current = next;
        setMessages(next);
    }, []);

    // Vue `nextTick()`: resolves once React has committed the pending updates to the DOM.
    const tickResolvers = useRef<Array<() => void>>([]);
    const [, setTick] = useState(0);

    useLayoutEffect(() => {
        if (tickResolvers.current.length === 0) {
            return;
        }

        const resolvers = tickResolvers.current;
        tickResolvers.current = [];
        resolvers.forEach((resolve) => resolve());
    });

    const nextTick = useCallback(
        () =>
            new Promise<void>((resolve) => {
                tickResolvers.current.push(resolve);
                setTick((tick) => tick + 1);
            }),
        [],
    );

    const csrfPropToken = (page.props as any).csrf_token as string | undefined;

    // Latest values for async code (Vue `.value` reads after an await)
    const latest = useLatest({
        endpoint,
        showSidebar,
        onSessionChange,
        config,
        currentSession,
        input,
        loading,
        selectedProvider,
        selectedModel,
        mentionedResources,
        trans,
        csrfPropToken,
    });

    // Get CSRF token
    function csrfToken(): string {
        return (
            latest.current.csrfPropToken ||
            (typeof document !== 'undefined'
                ? document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                : '') ||
            ''
        );
    }

    // Computed
    const availableProviders = useMemo(() => {
        if (!config) return [];

        return Object.entries(config.providers)
            .filter(([, p]) => p.configured)
            .map(([key, p]) => ({ key, ...p }));
    }, [config]);

    const availableModels = useMemo(() => {
        if (!config || !selectedProvider) return [];
        const provider = config.providers[selectedProvider];
        if (!provider) return [];

        return Object.entries(provider.models).map(([key, label]) => ({ key, label }));
    }, [config, selectedProvider]);

    const canSend = Boolean(input.trim() && !loading && config?.configured);

    function canSendNow(): boolean {
        const current = latest.current;

        return Boolean(current.input.trim() && !current.loading && current.config?.configured);
    }

    const currentProviderLabel = useMemo(() => {
        if (!selectedProvider || !config) return '';

        return config.providers[selectedProvider]?.label || selectedProvider;
    }, [config, selectedProvider]);

    const currentModelLabel = useMemo(() => {
        if (!selectedModel || !selectedProvider || !config) return '';
        const provider = config.providers[selectedProvider];

        return provider?.models[selectedModel] || selectedModel;
    }, [config, selectedProvider, selectedModel]);

    // Filtered resources for mention dropdown
    const filteredResources = useMemo(() => {
        if (!mentionQuery) return resources;
        const query = mentionQuery.toLowerCase();

        return resources.filter(
            (r) =>
                r.label.toLowerCase().includes(query) ||
                r.slug.toLowerCase().includes(query) ||
                r.singular.toLowerCase().includes(query),
        );
    }, [resources, mentionQuery]);

    // Load config
    async function loadConfig() {
        try {
            const response = await fetch(`${latest.current.endpoint}/config`, {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                credentials: 'same-origin',
            });
            const data: AIConfig | null = await response.json();
            setConfig(data);

            if (data?.default) {
                setSelectedProvider(data.default);
                const provider = data.providers[data.default];
                if (provider) {
                    setSelectedModel(provider.defaultModel);
                }
            }
        } catch (error) {
            console.error('Failed to load AI config:', error);
        } finally {
            setConfigLoading(false);
        }
    }

    // Load sessions
    async function loadSessions() {
        try {
            const response = await fetch(`${latest.current.endpoint}/sessions`, {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                credentials: 'same-origin',
            });
            const data = await response.json();
            setSessions(data.sessions || []);
        } catch (error) {
            console.error('Failed to load sessions:', error);
        }
    }

    // Load available resources for @ mentions
    async function loadResources() {
        try {
            const response = await fetch(`${latest.current.endpoint}/resources`, {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                credentials: 'same-origin',
            });
            const data = await response.json();
            setResources(data.resources || []);
        } catch (error) {
            console.error('Failed to load resources:', error);
        }
    }

    // Create new session
    async function createSession() {
        setCurrentSession(null);
        commitMessages([]);
        setInput('');
        clearMentions();
        inputRef.current?.focus();
    }

    // Load session
    async function loadSession(session: Session) {
        setCurrentSession(session);
        commitMessages(session.messages || []);
        setSelectedProvider(session.provider || latest.current.selectedProvider);
        setSelectedModel(session.model || latest.current.selectedModel);
        latest.current.onSessionChange?.(session);
        await nextTick();
        scrollToBottom();
    }

    // Delete session
    async function deleteSession(sessionId: string) {
        try {
            await fetch(`${latest.current.endpoint}/sessions/${sessionId}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                credentials: 'same-origin',
            });
            setSessions((previous) => previous.filter((s) => s.id !== sessionId));
            if (latest.current.currentSession?.id === sessionId) {
                setCurrentSession(null);
                commitMessages([]);
            }
        } catch (error) {
            console.error('Failed to delete session:', error);
        }
    }

    // Send message (non-streaming; not wired to the template, same as the Vue component)
    async function sendMessage() {
        if (!canSendNow()) return;

        const userMessage: Message = {
            role: 'user',
            content: latest.current.input.trim(),
            timestamp: Date.now(),
        };

        commitMessages([...messagesRef.current, userMessage]);
        setInput('');
        setLoading(true);

        // Reset textarea height
        if (inputRef.current) {
            inputRef.current.style.height = 'auto';
        }

        await nextTick();
        scrollToBottom();

        try {
            const current = latest.current;
            const response = await fetch(`${current.endpoint}/chat`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    messages: messagesRef.current.map((m) => ({ role: m.role, content: m.content })),
                    provider: current.selectedProvider,
                    model: current.selectedModel,
                    session_id: current.currentSession?.id,
                }),
            });

            const data = await response.json();

            commitMessages([
                ...messagesRef.current,
                {
                    role: 'assistant',
                    content: data.content,
                    timestamp: Date.now(),
                },
            ]);

            // Refresh sessions list to show updated titles
            if (latest.current.showSidebar) {
                await loadSessions();
            }
        } catch (error) {
            console.error('Failed to send message:', error);
            commitMessages([
                ...messagesRef.current,
                {
                    role: 'assistant',
                    content: latest.current.trans('laravilt-ai::ai.chat.error'),
                    timestamp: Date.now(),
                },
            ]);
        } finally {
            setLoading(false);
            await nextTick();
            scrollToBottom();
            inputRef.current?.focus();
        }
    }

    // Stream message
    async function streamMessage() {
        if (!canSendNow()) return;

        const start = latest.current;

        const userMessage: Message = {
            role: 'user',
            content: start.input.trim(),
            timestamp: Date.now(),
        };

        // Create session if this is a new conversation
        let sessionId = start.currentSession?.id;
        if (!sessionId && messagesRef.current.length === 0) {
            try {
                const sessionResponse = await fetch(`${start.endpoint}/sessions`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        title: userMessage.content.substring(0, 50) + (userMessage.content.length > 50 ? '...' : ''),
                        provider: start.selectedProvider,
                        model: start.selectedModel,
                    }),
                });
                const sessionData = await sessionResponse.json();
                if (sessionData.session) {
                    setCurrentSession(sessionData.session);
                    sessionId = sessionData.session.id;
                }
            } catch (error) {
                console.error('Failed to create session:', error);
            }
        }

        commitMessages([...messagesRef.current, userMessage]);
        setInput('');
        setLoading(true);
        setStreaming(true);

        // Reset textarea height safely
        await nextTick();
        const textareaEl = inputRef.current;
        if (textareaEl) {
            textareaEl.style.height = 'auto';
        }

        scrollToBottom();

        let assistantMessage: Message = {
            role: 'assistant',
            content: '',
            timestamp: Date.now(),
        };
        commitMessages([...messagesRef.current, assistantMessage]);

        // Replace the assistant message in place (keeps its position even if the list changed meanwhile)
        const setAssistantContent = (content: string) => {
            const updated: Message = { ...assistantMessage, content };
            commitMessages(messagesRef.current.map((m) => (m === assistantMessage ? updated : m)));
            assistantMessage = updated;
        };

        try {
            const current = latest.current;
            const response = await fetch(`${current.endpoint}/stream`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'text/event-stream',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    messages: messagesRef.current.slice(0, -1).map((m) => ({ role: m.role, content: m.content })),
                    provider: current.selectedProvider,
                    model: current.selectedModel,
                    session_id: sessionId,
                    mentioned_resources: current.mentionedResources.map((r) => r.slug),
                }),
            });

            // Check if response is ok
            if (!response.ok) {
                const errorText = await response.text();
                console.error('Stream response error:', response.status, errorText);
                throw new Error(`HTTP ${response.status}: ${errorText}`);
            }

            const reader = response.body?.getReader();
            const decoder = new TextDecoder();

            if (reader) {
                let buffer = '';
                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;

                    buffer += decoder.decode(value, { stream: true });
                    const lines = buffer.split('\n');

                    // Keep the last incomplete line in buffer
                    buffer = lines.pop() || '';

                    for (const line of lines) {
                        const trimmedLine = line.trim();
                        if (trimmedLine.startsWith('data: ')) {
                            const data = trimmedLine.slice(6);
                            if (data === '[DONE]') continue;

                            try {
                                const json = JSON.parse(data);
                                if (json.error) {
                                    console.error('AI error:', json.error);
                                    setAssistantContent(`Error: ${json.error}`);
                                } else if (json.content) {
                                    setAssistantContent(assistantMessage.content + json.content);
                                    await nextTick();
                                    scrollToBottom();
                                }
                            } catch (parseError) {
                                // Ignore parsing errors for incomplete chunks
                                console.debug('Parse error (may be incomplete):', data);
                            }
                        }
                    }
                }
            }
        } catch (error) {
            console.error('Stream error:', error);
            setAssistantContent(latest.current.trans('laravilt-ai::ai.chat.error'));
        } finally {
            setLoading(false);
            setStreaming(false);

            // Clear mentioned resources after sending
            clearMentions();

            // Refresh sessions list to show the new/updated session
            if (latest.current.showSidebar) {
                await loadSessions();
            }

            await nextTick();
            scrollToBottom();
            inputRef.current?.focus();
        }
    }

    function scrollToBottom() {
        if (chatContainerRef.current) {
            chatContainerRef.current.scrollTop = chatContainerRef.current.scrollHeight;
        }
    }

    function handleKeydown(event: KeyboardEvent<HTMLTextAreaElement>) {
        // Handle mention dropdown navigation
        if (showMentionDropdown && filteredResources.length > 0) {
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                setSelectedMentionIndex(Math.min(selectedMentionIndex + 1, filteredResources.length - 1));
                return;
            }
            if (event.key === 'ArrowUp') {
                event.preventDefault();
                setSelectedMentionIndex(Math.max(selectedMentionIndex - 1, 0));
                return;
            }
            if (event.key === 'Enter' || event.key === 'Tab') {
                event.preventDefault();
                selectMention(filteredResources[selectedMentionIndex]);
                return;
            }
            if (event.key === 'Escape') {
                event.preventDefault();
                setShowMentionDropdown(false);
                return;
            }
        }

        // Normal enter to send
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            streamMessage();
        }
    }

    async function copyMessage(content: string, index: number) {
        try {
            await navigator.clipboard.writeText(content);
            setCopiedMessageIndex(index);
            setTimeout(() => {
                setCopiedMessageIndex(null);
            }, 2000);
        } catch (error) {
            console.error('Failed to copy:', error);
        }
    }

    // Auto-resize textarea
    function autoResize(textarea: HTMLTextAreaElement) {
        textarea.style.height = 'auto';
        textarea.style.height = Math.min(textarea.scrollHeight, 200) + 'px';
    }

    // Handle input (v-model update + @ mention detection)
    function handleInput(event: ChangeEvent<HTMLTextAreaElement>) {
        const textarea = event.target;
        const value = textarea.value;
        setInput(value);

        autoResize(textarea);

        const cursorPos = textarea.selectionStart;
        const textBeforeCursor = value.substring(0, cursorPos);

        // Find the last @ symbol before cursor
        const lastAtIndex = textBeforeCursor.lastIndexOf('@');

        if (lastAtIndex !== -1) {
            // Check if @ is at start or preceded by whitespace
            const charBefore = lastAtIndex > 0 ? textBeforeCursor[lastAtIndex - 1] : ' ';
            if (charBefore === ' ' || charBefore === '\n' || lastAtIndex === 0) {
                const textAfterAt = textBeforeCursor.substring(lastAtIndex + 1);
                // Only show dropdown if there's no space after @
                if (!textAfterAt.includes(' ') && !textAfterAt.includes('\n')) {
                    setMentionStartIndex(lastAtIndex);
                    setMentionQuery(textAfterAt);
                    setShowMentionDropdown(true);
                    setSelectedMentionIndex(0);
                    return;
                }
            }
        }

        // Hide dropdown if no valid @ mention
        setShowMentionDropdown(false);
        setMentionQuery('');
        setMentionStartIndex(-1);
    }

    // Select a resource from the mention dropdown
    function selectMention(resource: Resource) {
        const textarea = inputRef.current;
        if (!textarea) return;

        const beforeMention = input.substring(0, mentionStartIndex);
        const afterCursor = input.substring(textarea.selectionStart);

        // Insert the mention with a visual marker
        const mentionText = `@${resource.label}`;
        setInput(beforeMention + mentionText + ' ' + afterCursor);

        // Add to mentioned resources if not already there
        if (!mentionedResources.find((r) => r.slug === resource.slug)) {
            setMentionedResources([
                ...mentionedResources,
                {
                    slug: resource.slug,
                    label: resource.label,
                },
            ]);
        }

        // Hide dropdown and reset
        setShowMentionDropdown(false);
        setMentionQuery('');
        setMentionStartIndex(-1);

        // Focus back on textarea
        nextTick().then(() => {
            inputRef.current?.focus();
        });
    }

    // Remove a mentioned resource
    function removeMention(slug: string) {
        setMentionedResources((previous) => previous.filter((r) => r.slug !== slug));
    }

    // Clear all mentions when creating new session
    function clearMentions() {
        setMentionedResources([]);
    }

    function formatTime(timestamp: number | undefined): string {
        if (!timestamp) return '';

        return new Date(timestamp).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    function renderMarkdown(content: string): string {
        if (!content) return '';

        return md.render(content);
    }

    useEffect(() => {
        (async () => {
            await loadConfig();
            await loadResources();
            if (latest.current.showSidebar) {
                await loadSessions();
            }
        })();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    // Vue `watch(selectedProvider)` — only reacts to changes, not the initial value
    const previousProvider = useRef(selectedProvider);

    useEffect(() => {
        if (previousProvider.current === selectedProvider) {
            return;
        }
        previousProvider.current = selectedProvider;

        if (config && selectedProvider) {
            const provider = config.providers[selectedProvider];
            if (provider) {
                setSelectedModel(provider.defaultModel);
            }
        }
    }, [selectedProvider, config]);

    return (
        <div className="flex h-full overflow-hidden rounded-xl border border-border bg-background shadow-sm">
            {/* Sidebar */}
            {showSidebar && (
                <aside
                    className={cn(
                        'flex flex-col border-e border-border bg-sidebar transition-all duration-300',
                        sidebarOpen ? 'w-72' : 'w-0 overflow-hidden',
                    )}
                >
                    {/* Sidebar Header */}
                    <div className="flex h-14 items-center justify-between border-b border-sidebar-border bg-sidebar px-4">
                        <Button
                            variant="outline"
                            size="sm"
                            className="gap-2 border-sidebar-border bg-sidebar-accent text-sidebar-foreground hover:bg-sidebar-accent/80"
                            onClick={createSession}
                        >
                            <Plus className="h-4 w-4" />
                            <span>{trans('laravilt-ai::ai.chat.new_chat')}</span>
                        </Button>
                    </div>

                    {/* Sessions List */}
                    <ScrollArea className="flex-1 px-3 py-3">
                        <div className="space-y-1">
                            {sessions.map((session) => (
                                <button
                                    key={session.id}
                                    className={cn(
                                        'group flex w-full items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors',
                                        currentSession?.id === session.id
                                            ? 'bg-sidebar-accent text-sidebar-accent-foreground'
                                            : 'text-sidebar-foreground/70 hover:bg-sidebar-accent/50 hover:text-sidebar-foreground',
                                    )}
                                    onClick={() => loadSession(session)}
                                >
                                    <MessageSquare className="h-4 w-4 shrink-0" />
                                    <span className="flex-1 truncate text-start">{session.title}</span>
                                    <DropdownMenu>
                                        <DropdownMenuTrigger asChild>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="h-6 w-6 shrink-0 opacity-0 transition-opacity group-hover:opacity-100"
                                                onClick={(event) => event.stopPropagation()}
                                            >
                                                <MoreHorizontal className="h-4 w-4" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuItem
                                                className="text-destructive focus:text-destructive"
                                                onClick={(event) => {
                                                    event.stopPropagation();
                                                    deleteSession(session.id);
                                                }}
                                            >
                                                <Trash2 className="me-2 h-4 w-4" />
                                                {trans('laravilt-ai::ai.chat.delete_session')}
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </button>
                            ))}
                        </div>
                    </ScrollArea>
                </aside>
            )}

            {/* Main Chat Area */}
            <div className="flex flex-1 flex-col bg-background">
                {/* Header */}
                <header className="flex h-14 items-center justify-between border-b border-border bg-card/50 px-4 backdrop-blur-sm">
                    <div className="flex items-center gap-3">
                        {showSidebar && (
                            <Button
                                variant="ghost"
                                size="icon"
                                className="text-muted-foreground hover:text-foreground"
                                onClick={() => setSidebarOpen(!sidebarOpen)}
                            >
                                {sidebarOpen ? <PanelLeftClose className="h-5 w-5" /> : <PanelLeft className="h-5 w-5" />}
                            </Button>
                        )}
                        <div className="flex items-center gap-2.5">
                            <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-gradient-to-br from-primary to-primary/70 shadow-sm">
                                <Sparkles className="h-5 w-5 text-primary-foreground" />
                            </div>
                            <span className="text-base font-semibold text-foreground">
                                {trans('laravilt-ai::ai.chat.title')}
                            </span>
                        </div>
                    </div>

                    {/* Model Selector */}
                    <div className="flex items-center gap-2">
                        <Select value={selectedProvider} onValueChange={setSelectedProvider}>
                            <SelectTrigger className="h-9 w-[140px] border-input bg-background text-sm shadow-sm">
                                <SelectValue placeholder={trans('laravilt-ai::ai.providers.select_provider')}>
                                    {currentProviderLabel}
                                </SelectValue>
                            </SelectTrigger>
                            <SelectContent>
                                {availableProviders.map((provider) => (
                                    <SelectItem key={provider.key} value={provider.key}>
                                        {provider.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <Select value={selectedModel} onValueChange={setSelectedModel}>
                            <SelectTrigger className="h-9 w-[180px] border-input bg-background text-sm shadow-sm">
                                <SelectValue placeholder={trans('laravilt-ai::ai.providers.select_model')}>
                                    {currentModelLabel}
                                </SelectValue>
                            </SelectTrigger>
                            <SelectContent>
                                {availableModels.map((model) => (
                                    <SelectItem key={model.key} value={model.key}>
                                        {model.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                </header>

                {/* Messages Area */}
                <div ref={chatContainerRef} className="flex-1 overflow-y-auto bg-muted/20">
                    {configLoading ? (
                        /* Loading Config */
                        <div className="flex h-full items-center justify-center">
                            <div className="flex flex-col items-center gap-3">
                                <Loader2 className="h-8 w-8 animate-spin text-primary" />
                                <span className="text-sm text-muted-foreground">Loading AI configuration...</span>
                            </div>
                        </div>
                    ) : !config?.configured ? (
                        /* Not Configured */
                        <div className="flex h-full flex-col items-center justify-center gap-4 p-8 text-center">
                            <div className="rounded-full bg-muted p-5 shadow-sm">
                                <Settings2 className="h-10 w-10 text-muted-foreground" />
                            </div>
                            <div>
                                <h3 className="text-lg font-semibold text-foreground">
                                    {trans('laravilt-ai::ai.errors.not_configured')}
                                </h3>
                                <p className="mt-2 max-w-sm text-sm text-muted-foreground">
                                    Please configure AI providers in your panel settings to enable the AI assistant.
                                </p>
                            </div>
                        </div>
                    ) : messages.length === 0 ? (
                        /* Empty State */
                        <div className="flex h-full flex-col items-center justify-center gap-8 p-8">
                            <div className="flex h-20 w-20 items-center justify-center rounded-2xl bg-gradient-to-br from-primary to-primary/60 shadow-lg">
                                <Sparkles className="h-10 w-10 text-primary-foreground" />
                            </div>
                            <div className="text-center">
                                <h2 className="text-2xl font-bold text-foreground">{trans('laravilt-ai::ai.chat.title')}</h2>
                                <p className="mt-3 max-w-lg text-muted-foreground">
                                    {trans('laravilt-ai::ai.chat.type_message')}
                                </p>
                            </div>
                            <div className="flex flex-wrap items-center justify-center gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="gap-2 text-xs"
                                    onClick={() => setInput(trans('laravilt-ai::ai.chat.understand_codebase'))}
                                >
                                    <Sparkles className="h-3 w-3" />
                                    {trans('laravilt-ai::ai.chat.understand_codebase')}
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="gap-2 text-xs"
                                    onClick={() => setInput(trans('laravilt-ai::ai.chat.generate_report'))}
                                >
                                    <Sparkles className="h-3 w-3" />
                                    {trans('laravilt-ai::ai.chat.generate_report')}
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="gap-2 text-xs"
                                    onClick={() => setInput(trans('laravilt-ai::ai.chat.debug_issue'))}
                                >
                                    <Sparkles className="h-3 w-3" />
                                    {trans('laravilt-ai::ai.chat.debug_issue')}
                                </Button>
                            </div>
                        </div>
                    ) : (
                        /* Messages */
                        <div className="mx-auto max-w-3xl space-y-6 px-4 py-6">
                            {messages.map((message, index) => (
                                <div
                                    key={index}
                                    className={cn(
                                        'group flex gap-3',
                                        message.role === 'user' ? 'justify-end' : 'justify-start',
                                    )}
                                >
                                    {/* Assistant Avatar */}
                                    {message.role === 'assistant' && (
                                        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-primary to-primary/70 shadow-sm">
                                            <Bot className="h-5 w-5 text-primary-foreground" />
                                        </div>
                                    )}

                                    {/* Message Content */}
                                    <div
                                        className={cn(
                                            'relative max-w-[80%] rounded-xl px-4 py-3 shadow-sm',
                                            message.role === 'user'
                                                ? 'bg-primary text-primary-foreground'
                                                : 'bg-card border border-border',
                                        )}
                                    >
                                        {message.role === 'user' ? (
                                            /* User messages: plain text with auto direction */
                                            <div dir="auto" className="whitespace-pre-wrap text-sm leading-relaxed text-start">
                                                {message.content}
                                            </div>
                                        ) : (
                                            /* Assistant messages: markdown with auto direction */
                                            <div
                                                dir="auto"
                                                className="prose prose-sm dark:prose-invert max-w-none text-sm leading-relaxed text-start prose-p:text-start prose-li:text-start prose-pre:bg-muted prose-pre:border prose-pre:border-border prose-pre:rounded-lg prose-pre:text-start prose-code:text-primary prose-code:bg-muted prose-code:px-1.5 prose-code:py-0.5 prose-code:rounded prose-code:before:content-[''] prose-code:after:content-['']"
                                                dangerouslySetInnerHTML={{ __html: renderMarkdown(message.content) }}
                                            />
                                        )}
                                        <div
                                            className={cn(
                                                'mt-2 flex items-center justify-between gap-3 text-xs',
                                                message.role === 'user' ? 'text-primary-foreground/60' : 'text-muted-foreground',
                                            )}
                                        >
                                            <span>{formatTime(message.timestamp)}</span>
                                            {/* Copy Button inline */}
                                            <button
                                                className={cn(
                                                    'flex h-6 w-6 items-center justify-center rounded-md opacity-0 transition-all group-hover:opacity-100',
                                                    message.role === 'user' ? 'hover:bg-primary-foreground/10' : 'hover:bg-muted',
                                                )}
                                                onClick={() => copyMessage(message.content, index)}
                                            >
                                                {copiedMessageIndex === index ? (
                                                    <Check className="h-3.5 w-3.5 text-green-500" />
                                                ) : (
                                                    <Copy className="h-3.5 w-3.5" />
                                                )}
                                            </button>
                                        </div>
                                    </div>

                                    {/* User Avatar */}
                                    {message.role === 'user' && (
                                        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-muted shadow-sm">
                                            <User className="h-5 w-5 text-muted-foreground" />
                                        </div>
                                    )}
                                </div>
                            ))}

                            {/* Typing Indicator */}
                            {loading && !streaming && (
                                <div className="flex gap-3">
                                    <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-primary to-primary/70 shadow-sm">
                                        <Bot className="h-5 w-5 text-primary-foreground" />
                                    </div>
                                    <div className="rounded-xl border border-border bg-card px-4 py-3 shadow-sm">
                                        <div className="flex items-center gap-1.5">
                                            <span
                                                className="h-2 w-2 animate-bounce rounded-full bg-primary/60"
                                                style={{ animationDelay: '0ms' }}
                                            ></span>
                                            <span
                                                className="h-2 w-2 animate-bounce rounded-full bg-primary/60"
                                                style={{ animationDelay: '150ms' }}
                                            ></span>
                                            <span
                                                className="h-2 w-2 animate-bounce rounded-full bg-primary/60"
                                                style={{ animationDelay: '300ms' }}
                                            ></span>
                                        </div>
                                    </div>
                                </div>
                            )}
                        </div>
                    )}
                </div>

                {/* Input Area */}
                <div className="border-t border-border bg-card/50 p-4 backdrop-blur-sm">
                    <div className="mx-auto max-w-3xl">
                        {/* Mentioned Resources Chips */}
                        {mentionedResources.length > 0 && (
                            <div className="mb-3 flex flex-wrap gap-2">
                                {mentionedResources.map((resource) => (
                                    <div
                                        key={resource.slug}
                                        className="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-3 py-1 text-xs font-medium text-primary"
                                    >
                                        <Database className="h-3 w-3" />
                                        <span>{resource.label}</span>
                                        <button
                                            type="button"
                                            className="ml-1 rounded-full p-0.5 hover:bg-primary/20"
                                            onClick={() => removeMention(resource.slug)}
                                        >
                                            <svg className="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path
                                                    strokeLinecap="round"
                                                    strokeLinejoin="round"
                                                    strokeWidth="2"
                                                    d="M6 18L18 6M6 6l12 12"
                                                />
                                            </svg>
                                        </button>
                                    </div>
                                ))}
                            </div>
                        )}

                        <div className="relative">
                            {/* Mention Dropdown */}
                            {showMentionDropdown && filteredResources.length > 0 && (
                                <div
                                    ref={mentionDropdownRef}
                                    className="absolute bottom-full left-0 z-50 mb-2 w-72 overflow-hidden rounded-xl border border-border bg-popover shadow-lg"
                                >
                                    <div className="px-3 py-2 text-xs font-medium text-muted-foreground border-b border-border">
                                        <div className="flex items-center gap-2">
                                            <AtSign className="h-3.5 w-3.5" />
                                            <span>{trans('laravilt-ai::ai.chat.mention_resource') || 'Mention a resource'}</span>
                                        </div>
                                    </div>
                                    <ScrollArea className="max-h-64">
                                        <div className="p-1">
                                            {filteredResources.map((resource, index) => (
                                                <button
                                                    key={resource.slug}
                                                    type="button"
                                                    className={cn(
                                                        'flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm transition-colors',
                                                        index === selectedMentionIndex
                                                            ? 'bg-primary text-primary-foreground'
                                                            : 'text-foreground hover:bg-accent',
                                                    )}
                                                    onClick={() => selectMention(resource)}
                                                    onMouseEnter={() => setSelectedMentionIndex(index)}
                                                >
                                                    <div
                                                        className={cn(
                                                            'flex h-8 w-8 items-center justify-center rounded-lg',
                                                            index === selectedMentionIndex ? 'bg-primary-foreground/20' : 'bg-muted',
                                                        )}
                                                    >
                                                        <Database
                                                            className={cn(
                                                                'h-4 w-4',
                                                                index === selectedMentionIndex
                                                                    ? 'text-primary-foreground'
                                                                    : 'text-muted-foreground',
                                                            )}
                                                        />
                                                    </div>
                                                    <div className="flex-1 text-start">
                                                        <div className="font-medium">{resource.label}</div>
                                                        <div
                                                            className={cn(
                                                                'text-xs',
                                                                index === selectedMentionIndex
                                                                    ? 'text-primary-foreground/70'
                                                                    : 'text-muted-foreground',
                                                            )}
                                                        >
                                                            {resource.count} {resource.count === 1 ? 'record' : 'records'}
                                                        </div>
                                                    </div>
                                                </button>
                                            ))}
                                        </div>
                                    </ScrollArea>
                                    <div className="border-t border-border bg-muted/50 px-3 py-2 text-xs text-muted-foreground">
                                        <kbd className="rounded bg-background px-1.5 py-0.5 font-mono text-[10px]">↑↓</kbd> navigate •{' '}
                                        <kbd className="rounded bg-background px-1.5 py-0.5 font-mono text-[10px]">Enter</kbd> select •{' '}
                                        <kbd className="rounded bg-background px-1.5 py-0.5 font-mono text-[10px]">Esc</kbd> close
                                    </div>
                                </div>
                            )}

                            <div className="flex items-end gap-3 rounded-2xl border border-input bg-background p-3 shadow-sm transition-all duration-200 focus-within:shadow-md focus-within:ring-2 focus-within:ring-primary/20 focus-within:border-primary/50">
                                <Textarea
                                    ref={inputRef}
                                    value={input}
                                    rows={1}
                                    className="min-h-[44px] flex-1 resize-none border-0 bg-transparent px-3 py-2.5 text-sm placeholder:text-muted-foreground focus-visible:ring-0"
                                    placeholder={trans('laravilt-ai::ai.chat.type_message')}
                                    disabled={loading || !config?.configured}
                                    onKeyDown={handleKeydown}
                                    onChange={handleInput}
                                />
                                <Button
                                    size="icon"
                                    className="h-10 w-10 shrink-0 rounded-xl shadow-sm transition-all duration-200 hover:scale-105 hover:shadow-md"
                                    disabled={!canSend}
                                    onClick={() => streamMessage()}
                                >
                                    {loading ? <Loader2 className="h-5 w-5 animate-spin" /> : <Send className="h-5 w-5" />}
                                </Button>
                            </div>
                        </div>
                        <p className="mt-3 text-center text-xs text-muted-foreground">
                            {trans('laravilt-ai::ai.chat.press_enter')}{' '}
                            <kbd className="rounded bg-muted px-1.5 py-0.5 font-mono text-[10px]">Enter</kbd>{' '}
                            {trans('laravilt-ai::ai.chat.to_send')} •{' '}
                            <kbd className="rounded bg-muted px-1.5 py-0.5 font-mono text-[10px]">Shift+Enter</kbd>{' '}
                            {trans('laravilt-ai::ai.chat.for_new_line')} •{' '}
                            <kbd className="rounded bg-muted px-1.5 py-0.5 font-mono text-[10px]">@</kbd>{' '}
                            {trans('laravilt-ai::ai.chat.to_mention')}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    );
}

export { AIChat };
