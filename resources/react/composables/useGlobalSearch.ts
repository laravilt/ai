import { useCallback, useRef, useState } from 'react';

interface SearchResult {
    id: string | number;
    title: string;
    subtitle?: string;
    url: string;
}

interface SearchGroup {
    resource: string;
    label: string;
    icon?: string;
    url: string;
    results: SearchResult[];
}

export function useGlobalSearch(endpoint = '/laravilt-ai/search') {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<SearchGroup[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [useAI, setUseAIState] = useState(true);

    // Mirrors the Vue ref so `search()` sees a value written synchronously before it.
    const useAIRef = useRef(true);

    const setUseAI = useCallback((value: boolean) => {
        useAIRef.current = value;
        setUseAIState(value);
    }, []);

    const searchTimeout = useRef<ReturnType<typeof setTimeout> | null>(null);

    const hasResults = results.length > 0;

    const totalResults = results.reduce((total, group) => total + group.results.length, 0);

    const search = useCallback(
        async (searchQuery: string): Promise<SearchGroup[]> => {
            if (!searchQuery.trim()) {
                setResults([]);
                return [];
            }

            setLoading(true);
            setError(null);

            try {
                const response = await fetch(
                    `${endpoint}?query=${encodeURIComponent(searchQuery)}&useAI=${useAIRef.current}`,
                );
                const data = await response.json();
                const next: SearchGroup[] = data.results || [];
                setResults(next);
                return next;
            } catch (e) {
                setError(e instanceof Error ? e.message : 'Search failed');
                setResults([]);
                throw e;
            } finally {
                setLoading(false);
            }
        },
        [endpoint],
    );

    const debouncedSearch = useCallback(
        (searchQuery: string, delay = 300): Promise<SearchGroup[]> => {
            if (searchTimeout.current) {
                clearTimeout(searchTimeout.current);
            }

            return new Promise<SearchGroup[]>((resolve, reject) => {
                searchTimeout.current = setTimeout(async () => {
                    try {
                        const result = await search(searchQuery);
                        resolve(result);
                    } catch (e) {
                        reject(e);
                    }
                }, delay);
            });
        },
        [search],
    );

    const clear = useCallback(() => {
        setQuery('');
        setResults([]);
        setError(null);
    }, []);

    return {
        query,
        setQuery,
        results,
        loading,
        error,
        useAI,
        setUseAI,
        hasResults,
        totalResults,
        search,
        debouncedSearch,
        clear,
    };
}
