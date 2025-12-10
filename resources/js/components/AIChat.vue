<script setup lang="ts">
import { ref, computed, watch, nextTick, onMounted } from 'vue'
import {
  Send,
  Loader2,
  Bot,
  User,
  Plus,
  Trash2,
  Settings2,
  ChevronDown,
  Copy,
  Check,
  Sparkles,
} from 'lucide-vue-next'

interface Message {
  role: 'system' | 'user' | 'assistant'
  content: string
  timestamp?: number
}

interface Session {
  id: string
  title: string
  provider?: string
  model?: string
  messages: Message[]
  created_at: string
  updated_at: string
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

const props = withDefaults(
  defineProps<{
    initialSession?: Session
    showSidebar?: boolean
    endpoint?: string
  }>(),
  {
    showSidebar: true,
    endpoint: '/laravilt-ai',
  }
)

const emit = defineEmits<{
  (e: 'sessionChange', session: Session): void
}>()

// State
const config = ref<AIConfig | null>(null)
const sessions = ref<Session[]>([])
const currentSession = ref<Session | null>(props.initialSession || null)
const messages = ref<Message[]>([])
const input = ref('')
const loading = ref(false)
const streaming = ref(false)
const configLoading = ref(true)

const selectedProvider = ref<string>('')
const selectedModel = ref<string>('')

const chatContainerRef = ref<HTMLElement | null>(null)
const inputRef = ref<HTMLTextAreaElement | null>(null)
const copiedMessageIndex = ref<number | null>(null)

// Computed
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

const canSend = computed(() => {
  return input.value.trim() && !loading.value && config.value?.configured
})

// Load config
async function loadConfig() {
  try {
    const response = await fetch(`${props.endpoint}/config`)
    config.value = await response.json()

    if (config.value?.default) {
      selectedProvider.value = config.value.default
      const provider = config.value.providers[config.value.default]
      if (provider) {
        selectedModel.value = provider.defaultModel
      }
    }
  } catch (error) {
    console.error('Failed to load AI config:', error)
  } finally {
    configLoading.value = false
  }
}

// Load sessions
async function loadSessions() {
  try {
    const response = await fetch(`${props.endpoint}/sessions`)
    const data = await response.json()
    sessions.value = data.sessions || []
  } catch (error) {
    console.error('Failed to load sessions:', error)
  }
}

// Create new session
async function createSession() {
  try {
    const response = await fetch(`${props.endpoint}/sessions`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        title: 'New Chat',
        provider: selectedProvider.value,
        model: selectedModel.value,
      }),
    })
    const data = await response.json()
    currentSession.value = data.session
    messages.value = []
    await loadSessions()
    emit('sessionChange', data.session)
  } catch (error) {
    console.error('Failed to create session:', error)
  }
}

// Load session
async function loadSession(session: Session) {
  currentSession.value = session
  messages.value = session.messages || []
  selectedProvider.value = session.provider || selectedProvider.value
  selectedModel.value = session.model || selectedModel.value
  emit('sessionChange', session)
  await nextTick()
  scrollToBottom()
}

// Delete session
async function deleteSession(sessionId: string) {
  try {
    await fetch(`${props.endpoint}/sessions/${sessionId}`, {
      method: 'DELETE',
    })
    sessions.value = sessions.value.filter((s) => s.id !== sessionId)
    if (currentSession.value?.id === sessionId) {
      currentSession.value = null
      messages.value = []
    }
  } catch (error) {
    console.error('Failed to delete session:', error)
  }
}

// Send message
async function sendMessage() {
  if (!canSend.value) return

  const userMessage: Message = {
    role: 'user',
    content: input.value.trim(),
    timestamp: Date.now(),
  }

  messages.value.push(userMessage)
  const userInput = input.value
  input.value = ''
  loading.value = true

  await nextTick()
  scrollToBottom()

  try {
    const response = await fetch(`${props.endpoint}/chat`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        messages: messages.value.map((m) => ({ role: m.role, content: m.content })),
        provider: selectedProvider.value,
        model: selectedModel.value,
        session_id: currentSession.value?.id,
      }),
    })

    const data = await response.json()

    messages.value.push({
      role: 'assistant',
      content: data.content,
      timestamp: Date.now(),
    })
  } catch (error) {
    console.error('Failed to send message:', error)
    messages.value.push({
      role: 'assistant',
      content: 'Sorry, an error occurred. Please try again.',
      timestamp: Date.now(),
    })
  } finally {
    loading.value = false
    await nextTick()
    scrollToBottom()
    inputRef.value?.focus()
  }
}

// Stream message (alternative)
async function streamMessage() {
  if (!canSend.value) return

  const userMessage: Message = {
    role: 'user',
    content: input.value.trim(),
    timestamp: Date.now(),
  }

  messages.value.push(userMessage)
  input.value = ''
  loading.value = true
  streaming.value = true

  await nextTick()
  scrollToBottom()

  const assistantMessage: Message = {
    role: 'assistant',
    content: '',
    timestamp: Date.now(),
  }
  messages.value.push(assistantMessage)

  try {
    const response = await fetch(`${props.endpoint}/stream`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        messages: messages.value
          .slice(0, -1)
          .map((m) => ({ role: m.role, content: m.content })),
        provider: selectedProvider.value,
        model: selectedModel.value,
      }),
    })

    const reader = response.body?.getReader()
    const decoder = new TextDecoder()

    if (reader) {
      while (true) {
        const { done, value } = await reader.read()
        if (done) break

        const chunk = decoder.decode(value)
        const lines = chunk.split('\n')

        for (const line of lines) {
          if (line.startsWith('data: ')) {
            const data = line.slice(6)
            if (data === '[DONE]') break

            try {
              const json = JSON.parse(data)
              if (json.content) {
                assistantMessage.content += json.content
                await nextTick()
                scrollToBottom()
              }
            } catch {
              // Ignore parsing errors
            }
          }
        }
      }
    }
  } catch (error) {
    console.error('Stream error:', error)
    assistantMessage.content = 'Sorry, an error occurred. Please try again.'
  } finally {
    loading.value = false
    streaming.value = false
    await nextTick()
    scrollToBottom()
    inputRef.value?.focus()
  }
}

function scrollToBottom() {
  if (chatContainerRef.value) {
    chatContainerRef.value.scrollTop = chatContainerRef.value.scrollHeight
  }
}

function handleKeydown(event: KeyboardEvent) {
  if (event.key === 'Enter' && !event.shiftKey) {
    event.preventDefault()
    sendMessage()
  }
}

async function copyMessage(content: string, index: number) {
  try {
    await navigator.clipboard.writeText(content)
    copiedMessageIndex.value = index
    setTimeout(() => {
      copiedMessageIndex.value = null
    }, 2000)
  } catch (error) {
    console.error('Failed to copy:', error)
  }
}

// Auto-resize textarea
function autoResize(event: Event) {
  const textarea = event.target as HTMLTextAreaElement
  textarea.style.height = 'auto'
  textarea.style.height = Math.min(textarea.scrollHeight, 200) + 'px'
}

onMounted(async () => {
  await loadConfig()
  if (props.showSidebar) {
    await loadSessions()
  }
})

watch(selectedProvider, (newProvider) => {
  if (config.value && newProvider) {
    const provider = config.value.providers[newProvider]
    if (provider) {
      selectedModel.value = provider.defaultModel
    }
  }
})
</script>

<template>
  <div class="flex h-full overflow-hidden rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
    <!-- Sidebar -->
    <div
      v-if="showSidebar"
      class="w-64 flex-shrink-0 border-r border-gray-200 dark:border-gray-700"
    >
      <div class="flex h-full flex-col">
        <!-- New Chat Button -->
        <div class="p-3">
          <button
            type="button"
            class="flex w-full items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
            @click="createSession"
          >
            <Plus class="h-4 w-4" />
            New Chat
          </button>
        </div>

        <!-- Sessions List -->
        <div class="flex-1 overflow-y-auto p-2">
          <div
            v-for="session in sessions"
            :key="session.id"
            class="group mb-1 flex cursor-pointer items-center gap-2 rounded-lg px-3 py-2 text-sm transition"
            :class="[
              currentSession?.id === session.id
                ? 'bg-primary-50 text-primary-900 dark:bg-primary-900/20 dark:text-primary-100'
                : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700',
            ]"
            @click="loadSession(session)"
          >
            <Bot class="h-4 w-4 flex-shrink-0" />
            <span class="flex-1 truncate">{{ session.title }}</span>
            <button
              type="button"
              class="rounded p-1 opacity-0 transition hover:bg-gray-200 group-hover:opacity-100 dark:hover:bg-gray-600"
              @click.stop="deleteSession(session.id)"
            >
              <Trash2 class="h-3.5 w-3.5 text-gray-500 dark:text-gray-400" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Chat Area -->
    <div class="flex flex-1 flex-col">
      <!-- Header -->
      <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-700">
        <div class="flex items-center gap-2">
          <Sparkles class="h-5 w-5 text-purple-500" />
          <span class="font-medium text-gray-900 dark:text-gray-100">AI Assistant</span>
        </div>

        <!-- Model Selector -->
        <div class="flex items-center gap-2">
          <select
            v-model="selectedProvider"
            class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
          >
            <option v-for="provider in availableProviders" :key="provider.key" :value="provider.key">
              {{ provider.label }}
            </option>
          </select>

          <select
            v-model="selectedModel"
            class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
          >
            <option v-for="model in availableModels" :key="model.key" :value="model.key">
              {{ model.label }}
            </option>
          </select>
        </div>
      </div>

      <!-- Messages -->
      <div ref="chatContainerRef" class="flex-1 overflow-y-auto p-4">
        <!-- Loading Config -->
        <div
          v-if="configLoading"
          class="flex h-full items-center justify-center text-gray-500 dark:text-gray-400"
        >
          <Loader2 class="h-6 w-6 animate-spin" />
        </div>

        <!-- Not Configured -->
        <div
          v-else-if="!config?.configured"
          class="flex h-full flex-col items-center justify-center text-center text-gray-500 dark:text-gray-400"
        >
          <Settings2 class="mb-4 h-12 w-12 text-gray-300 dark:text-gray-600" />
          <p class="font-medium">AI is not configured</p>
          <p class="mt-1 text-sm">Add an API key in your configuration to enable AI features.</p>
        </div>

        <!-- Empty State -->
        <div
          v-else-if="messages.length === 0"
          class="flex h-full flex-col items-center justify-center text-center text-gray-500 dark:text-gray-400"
        >
          <Bot class="mb-4 h-12 w-12 text-gray-300 dark:text-gray-600" />
          <p class="font-medium">Start a conversation</p>
          <p class="mt-1 text-sm">Type a message below to begin chatting with the AI.</p>
        </div>

        <!-- Messages -->
        <div v-else class="space-y-4">
          <div
            v-for="(message, index) in messages"
            :key="index"
            class="flex gap-3"
            :class="message.role === 'user' ? 'justify-end' : 'justify-start'"
          >
            <div
              v-if="message.role === 'assistant'"
              class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-purple-100 dark:bg-purple-900/30"
            >
              <Bot class="h-4 w-4 text-purple-600 dark:text-purple-400" />
            </div>

            <div
              class="group relative max-w-[80%] rounded-2xl px-4 py-2"
              :class="[
                message.role === 'user'
                  ? 'bg-primary-600 text-white'
                  : 'bg-gray-100 text-gray-900 dark:bg-gray-700 dark:text-gray-100',
              ]"
            >
              <div class="whitespace-pre-wrap text-sm">{{ message.content }}</div>

              <!-- Copy Button -->
              <button
                type="button"
                class="absolute -bottom-6 right-0 rounded p-1 text-gray-400 opacity-0 transition hover:bg-gray-100 group-hover:opacity-100 dark:hover:bg-gray-700"
                @click="copyMessage(message.content, index)"
              >
                <Check v-if="copiedMessageIndex === index" class="h-3.5 w-3.5 text-green-500" />
                <Copy v-else class="h-3.5 w-3.5" />
              </button>
            </div>

            <div
              v-if="message.role === 'user'"
              class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-primary-100 dark:bg-primary-900/30"
            >
              <User class="h-4 w-4 text-primary-600 dark:text-primary-400" />
            </div>
          </div>

          <!-- Loading indicator -->
          <div v-if="loading && !streaming" class="flex gap-3">
            <div
              class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-purple-100 dark:bg-purple-900/30"
            >
              <Loader2 class="h-4 w-4 animate-spin text-purple-600 dark:text-purple-400" />
            </div>
            <div class="rounded-2xl bg-gray-100 px-4 py-2 dark:bg-gray-700">
              <div class="flex items-center gap-1">
                <span class="h-2 w-2 animate-bounce rounded-full bg-gray-400 dark:bg-gray-500"></span>
                <span
                  class="h-2 w-2 animate-bounce rounded-full bg-gray-400 dark:bg-gray-500"
                  style="animation-delay: 0.1s"
                ></span>
                <span
                  class="h-2 w-2 animate-bounce rounded-full bg-gray-400 dark:bg-gray-500"
                  style="animation-delay: 0.2s"
                ></span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Input -->
      <div class="border-t border-gray-200 p-4 dark:border-gray-700">
        <div class="flex items-end gap-3">
          <div class="relative flex-1">
            <textarea
              ref="inputRef"
              v-model="input"
              rows="1"
              class="block w-full resize-none rounded-xl border border-gray-200 bg-white py-3 pl-4 pr-12 text-sm placeholder:text-gray-400 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 dark:placeholder:text-gray-500"
              placeholder="Type your message..."
              :disabled="loading || !config?.configured"
              @keydown="handleKeydown"
              @input="autoResize"
            />
          </div>
          <button
            type="button"
            class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-primary-600 text-white transition hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="!canSend"
            @click="sendMessage"
          >
            <Loader2 v-if="loading" class="h-5 w-5 animate-spin" />
            <Send v-else class="h-5 w-5" />
          </button>
        </div>
        <p class="mt-2 text-center text-xs text-gray-400 dark:text-gray-500">
          Press Enter to send, Shift+Enter for new line
        </p>
      </div>
    </div>
  </div>
</template>
