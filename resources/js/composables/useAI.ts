import { ref, computed } from 'vue'

interface Message {
  role: 'system' | 'user' | 'assistant'
  content: string
  timestamp?: number
}

interface Provider {
  name: string
  label: string
  models: Record<string, string>
  defaultModel: string
  configured: boolean
}

interface AIConfig {
  configured: boolean
  default: string
  providers: Record<string, Provider>
}

const config = ref<AIConfig | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)

export function useAI(endpoint = '/laravilt-ai') {
  const selectedProvider = ref<string>('')
  const selectedModel = ref<string>('')

  const isConfigured = computed(() => config.value?.configured ?? false)

  const availableProviders = computed(() => {
    if (!config.value) return []
    return Object.entries(config.value.providers)
      .filter(([, p]) => p.configured)
      .map(([key, p]) => ({ key, ...p }))
  })

  const availableModels = computed(() => {
    if (!config.value || !selectedProvider.value) return []
    const provider = config.value.providers[selectedProvider.value]
    if (!provider) return []
    return Object.entries(provider.models).map(([key, label]) => ({ key, label }))
  })

  function selectDefaults(data: AIConfig | null) {
    if (data?.default) {
      selectedProvider.value = data.default
      const provider = data.providers[data.default]
      if (provider) {
        selectedModel.value = provider.defaultModel
      }
    }
  }

  async function loadConfig() {
    if (config.value) {
      // The config is shared, but selections are per composable instance: initialize them for later callers too.
      if (!selectedProvider.value) {
        selectDefaults(config.value)
      }

      return config.value
    }

    loading.value = true
    error.value = null

    try {
      const response = await fetch(`${endpoint}/config`)
      config.value = await response.json()
      selectDefaults(config.value)

      return config.value
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to load AI config'
      throw e
    } finally {
      loading.value = false
    }
  }

  async function chat(messages: Message[], options: { provider?: string; model?: string } = {}) {
    loading.value = true
    error.value = null

    try {
      const response = await fetch(`${endpoint}/chat`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          messages: messages.map((m) => ({ role: m.role, content: m.content })),
          provider: options.provider || selectedProvider.value,
          model: options.model || selectedModel.value,
        }),
      })

      return await response.json()
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to send message'
      throw e
    } finally {
      loading.value = false
    }
  }

  async function* streamChat(
    messages: Message[],
    options: { provider?: string; model?: string } = {}
  ): AsyncGenerator<string> {
    loading.value = true
    error.value = null

    try {
      const response = await fetch(`${endpoint}/stream`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          messages: messages.map((m) => ({ role: m.role, content: m.content })),
          provider: options.provider || selectedProvider.value,
          model: options.model || selectedModel.value,
        }),
      })

      const reader = response.body?.getReader()
      const decoder = new TextDecoder()

      if (reader) {
        let buffer = ''

        while (true) {
          const { done, value } = await reader.read()
          if (done) break

          // SSE records can span chunks: keep the trailing incomplete line for the next read.
          buffer += decoder.decode(value, { stream: true })
          const lines = buffer.split('\n')
          buffer = lines.pop() ?? ''

          for (const line of lines) {
            const trimmedLine = line.trim()
            if (trimmedLine.startsWith('data: ')) {
              const data = trimmedLine.slice(6)
              if (data === '[DONE]') return

              try {
                const json = JSON.parse(data)
                if (json.content) {
                  yield json.content
                }
              } catch {
                // Ignore parsing errors
              }
            }
          }
        }
      }
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Stream error'
      throw e
    } finally {
      loading.value = false
    }
  }

  return {
    config,
    loading,
    error,
    selectedProvider,
    selectedModel,
    isConfigured,
    availableProviders,
    availableModels,
    loadConfig,
    chat,
    streamChat,
  }
}
