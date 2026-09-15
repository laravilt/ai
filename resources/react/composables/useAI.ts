import { useCallback, useMemo, useRef, useState, useSyncExternalStore } from 'react';

interface Message {
    role: 'system' | 'user' | 'assistant';
    content: string;
    timestamp?: number;
}

interface Provider {
    name: string;
    label: string;
    models: Record<string, string>;
    defaultModel: string;
    configured: boolean;
}

interface AIConfig {
    configured: boolean;
    default: string;
    providers: Record<string, Provider>;
}

/**
 * Module-level shared state (the Vue composable keeps `config`, `loading` and `error`
 * as module-level refs shared by every `useAI()` caller). Tiny external store.
 */
interface AIState {
    config: AIConfig | null;
    loading: boolean;
    error: string | null;
}

let state: AIState = {
    config: null,
    loading: false,
    error: null,
};

const listeners = new Set<() => void>();

function setState(patch: Partial<AIState>): void {
    state = { ...state, ...patch };
    listeners.forEach((listener) => listener());
}

function subscribe(listener: () => void): () => void {
    listeners.add(listener);

    return () => {
        listeners.delete(listener);
    };
}

function getSnapshot(): AIState {
    return state;
}

export function useAI(endpoint = '/laravilt-ai') {
    const { config, loading, error } = useSyncExternalStore(subscribe, getSnapshot, getSnapshot);

    const [selectedProvider, setSelectedProviderState] = useState<string>('');
    const [selectedModel, setSelectedModelState] = useState<string>('');

    // Refs mirror the Vue refs so async code reads the value written synchronously before it.
    const selectedProviderRef = useRef<string>('');
    const selectedModelRef = useRef<string>('');

    const setSelectedProvider = useCallback((value: string) => {
        selectedProviderRef.current = value;
        setSelectedProviderState(value);
    }, []);

    const setSelectedModel = useCallback((value: string) => {
        selectedModelRef.current = value;
        setSelectedModelState(value);
    }, []);

    const isConfigured = config?.configured ?? false;

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

    const loadConfig = useCallback(async (): Promise<AIConfig | null> => {
        const selectDefaults = (data: AIConfig | null) => {
            if (data?.default) {
                setSelectedProvider(data.default);
                const provider = data.providers[data.default];
                if (provider) {
                    setSelectedModel(provider.defaultModel);
                }
            }
        };

        if (state.config) {
            // The config is shared, but selections are per hook instance: initialize them for later callers too.
            if (!selectedProviderRef.current) {
                selectDefaults(state.config);
            }

            return state.config;
        }

        setState({ loading: true, error: null });

        try {
            const response = await fetch(`${endpoint}/config`);
            const data: AIConfig | null = await response.json();
            setState({ config: data });
            selectDefaults(data);

            return state.config;
        } catch (e) {
            setState({ error: e instanceof Error ? e.message : 'Failed to load AI config' });
            throw e;
        } finally {
            setState({ loading: false });
        }
    }, [endpoint, setSelectedProvider, setSelectedModel]);

    const chat = useCallback(
        async (messages: Message[], options: { provider?: string; model?: string } = {}): Promise<any> => {
            setState({ loading: true, error: null });

            try {
                const response = await fetch(`${endpoint}/chat`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        messages: messages.map((m) => ({ role: m.role, content: m.content })),
                        provider: options.provider || selectedProviderRef.current,
                        model: options.model || selectedModelRef.current,
                    }),
                });

                return await response.json();
            } catch (e) {
                setState({ error: e instanceof Error ? e.message : 'Failed to send message' });
                throw e;
            } finally {
                setState({ loading: false });
            }
        },
        [endpoint],
    );

    const streamChat = useCallback(
        async function* (
            messages: Message[],
            options: { provider?: string; model?: string } = {},
        ): AsyncGenerator<string> {
            setState({ loading: true, error: null });

            try {
                const response = await fetch(`${endpoint}/stream`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        messages: messages.map((m) => ({ role: m.role, content: m.content })),
                        provider: options.provider || selectedProviderRef.current,
                        model: options.model || selectedModelRef.current,
                    }),
                });

                const reader = response.body?.getReader();
                const decoder = new TextDecoder();

                if (reader) {
                    let buffer = '';

                    while (true) {
                        const { done, value } = await reader.read();
                        if (done) break;

                        // SSE records can span chunks: keep the trailing incomplete line for the next read.
                        buffer += decoder.decode(value, { stream: true });
                        const lines = buffer.split('\n');
                        buffer = lines.pop() ?? '';

                        for (const line of lines) {
                            const trimmedLine = line.trim();
                            if (trimmedLine.startsWith('data: ')) {
                                const data = trimmedLine.slice(6);
                                if (data === '[DONE]') return;

                                try {
                                    const json = JSON.parse(data);
                                    if (json.content) {
                                        yield json.content;
                                    }
                                } catch {
                                    // Ignore parsing errors
                                }
                            }
                        }
                    }
                }
            } catch (e) {
                setState({ error: e instanceof Error ? e.message : 'Stream error' });
                throw e;
            } finally {
                setState({ loading: false });
            }
        },
        [endpoint],
    );

    return {
        config,
        loading,
        error,
        selectedProvider,
        selectedModel,
        setSelectedProvider,
        setSelectedModel,
        isConfigured,
        availableProviders,
        availableModels,
        loadConfig,
        chat,
        streamChat,
    };
}
