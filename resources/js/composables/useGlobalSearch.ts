import { ref, computed } from 'vue'

interface SearchResult {
  id: string | number
  title: string
  subtitle?: string
  url: string
}

interface SearchGroup {
  resource: string
  label: string
  icon?: string
  url: string
  results: SearchResult[]
}

export function useGlobalSearch(endpoint = '/laravilt-ai/search') {
  const query = ref('')
  const results = ref<SearchGroup[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)
  const useAI = ref(true)

  let searchTimeout: ReturnType<typeof setTimeout> | null = null

  const hasResults = computed(() => results.value.length > 0)

  const totalResults = computed(() => {
    return results.value.reduce((total, group) => total + group.results.length, 0)
  })

  async function search(searchQuery: string) {
    if (!searchQuery.trim()) {
      results.value = []
      return []
    }

    loading.value = true
    error.value = null

    try {
      const response = await fetch(
        `${endpoint}?query=${encodeURIComponent(searchQuery)}&useAI=${useAI.value}`
      )
      const data = await response.json()
      results.value = data.results || []
      return results.value
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Search failed'
      results.value = []
      throw e
    } finally {
      loading.value = false
    }
  }

  function debouncedSearch(searchQuery: string, delay = 300) {
    if (searchTimeout) {
      clearTimeout(searchTimeout)
    }

    return new Promise<SearchGroup[]>((resolve, reject) => {
      searchTimeout = setTimeout(async () => {
        try {
          const result = await search(searchQuery)
          resolve(result)
        } catch (e) {
          reject(e)
        }
      }, delay)
    })
  }

  function clear() {
    query.value = ''
    results.value = []
    error.value = null
  }

  return {
    query,
    results,
    loading,
    error,
    useAI,
    hasResults,
    totalResults,
    search,
    debouncedSearch,
    clear,
  }
}
