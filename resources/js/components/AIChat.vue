<script setup lang="ts">
import { ref, computed, watch, nextTick, onMounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import {
  Send,
  Loader2,
  Bot,
  User,
  Plus,
  Trash2,
  Settings2,
  Copy,
  Check,
  Sparkles,
  PanelLeftClose,
  PanelLeft,
  MoreHorizontal,
  Pencil,
  MessageSquare,
} from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { ScrollArea } from '@/components/ui/scroll-area'
import { Textarea } from '@/components/ui/textarea'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { useLocalization } from '@laravilt/support/composables'

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

const { trans } = useLocalization()
const page = usePage()

// State
const config = ref<AIConfig | null>(null)
const sessions = ref<Session[]>([])
const currentSession = ref<Session | null>(props.initialSession || null)
const messages = ref<Message[]>([])
const input = ref('')
const loading = ref(false)
const streaming = ref(false)
const configLoading = ref(true)
const sidebarOpen = ref(true)

const selectedProvider = ref<string>('')
const selectedModel = ref<string>('')

const chatContainerRef = ref<HTMLElement | null>(null)
const inputRef = ref<HTMLTextAreaElement | null>(null)
const copiedMessageIndex = ref<number | null>(null)

// Get CSRF token
const csrfToken = computed(() => {
  return (page.props as any).csrf_token || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
})

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

const currentProviderLabel = computed(() => {
  if (!selectedProvider.value || !config.value) return ''
  return config.value.providers[selectedProvider.value]?.label || selectedProvider.value
})

const currentModelLabel = computed(() => {
  if (!selectedModel.value || !selectedProvider.value || !config.value) return ''
  const provider = config.value.providers[selectedProvider.value]
  return provider?.models[selectedModel.value] || selectedModel.value
})

// Load config
async function loadConfig() {
  try {
    const response = await fetch(`${props.endpoint}/config`, {
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrfToken.value,
      },
      credentials: 'same-origin',
    })
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
    const response = await fetch(`${props.endpoint}/sessions`, {
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrfToken.value,
      },
      credentials: 'same-origin',
    })
    const data = await response.json()
    sessions.value = data.sessions || []
  } catch (error) {
    console.error('Failed to load sessions:', error)
  }
}

// Create new session
async function createSession() {
  currentSession.value = null
  messages.value = []
  input.value = ''
  inputRef.value?.focus()
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
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrfToken.value,
      },
      credentials: 'same-origin',
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

  // Reset textarea height
  if (inputRef.value) {
    inputRef.value.style.height = 'auto'
  }

  await nextTick()
  scrollToBottom()

  try {
    const response = await fetch(`${props.endpoint}/chat`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrfToken.value,
      },
      credentials: 'same-origin',
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

    // Refresh sessions list to show updated titles
    if (props.showSidebar) {
      await loadSessions()
    }
  } catch (error) {
    console.error('Failed to send message:', error)
    messages.value.push({
      role: 'assistant',
      content: trans('laravilt-ai::ai.chat.error'),
      timestamp: Date.now(),
    })
  } finally {
    loading.value = false
    await nextTick()
    scrollToBottom()
    inputRef.value?.focus()
  }
}

// Stream message
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

  if (inputRef.value) {
    inputRef.value.style.height = 'auto'
  }

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
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'text/event-stream',
        'X-CSRF-TOKEN': csrfToken.value,
      },
      credentials: 'same-origin',
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
    assistantMessage.content = trans('laravilt-ai::ai.chat.error')
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

function formatTime(timestamp: number | undefined): string {
  if (!timestamp) return ''
  return new Date(timestamp).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
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
  <div class="flex h-full bg-background">
    <!-- Sidebar -->
    <aside
      v-if="showSidebar"
      :class="[
        'flex flex-col border-e border-border bg-sidebar transition-all duration-300',
        sidebarOpen ? 'w-72' : 'w-0 overflow-hidden'
      ]"
    >
      <!-- Sidebar Header -->
      <div class="flex h-14 items-center justify-between border-b border-sidebar-border bg-sidebar px-4">
        <Button
          variant="outline"
          size="sm"
          class="gap-2 border-sidebar-border bg-sidebar-accent text-sidebar-foreground hover:bg-sidebar-accent/80"
          @click="createSession"
        >
          <Plus class="h-4 w-4" />
          <span>{{ trans('laravilt-ai::ai.chat.new_chat') }}</span>
        </Button>
      </div>

      <!-- Sessions List -->
      <ScrollArea class="flex-1 px-3 py-3">
        <div class="space-y-1">
          <button
            v-for="session in sessions"
            :key="session.id"
            :class="[
              'group flex w-full items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors',
              currentSession?.id === session.id
                ? 'bg-sidebar-accent text-sidebar-accent-foreground'
                : 'text-sidebar-foreground/70 hover:bg-sidebar-accent/50 hover:text-sidebar-foreground'
            ]"
            @click="loadSession(session)"
          >
            <MessageSquare class="h-4 w-4 shrink-0" />
            <span class="flex-1 truncate text-start">{{ session.title }}</span>
            <DropdownMenu>
              <DropdownMenuTrigger as-child>
                <Button
                  variant="ghost"
                  size="icon"
                  class="h-6 w-6 shrink-0 opacity-0 transition-opacity group-hover:opacity-100"
                  @click.stop
                >
                  <MoreHorizontal class="h-4 w-4" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end">
                <DropdownMenuItem @click.stop="deleteSession(session.id)" class="text-destructive focus:text-destructive">
                  <Trash2 class="me-2 h-4 w-4" />
                  {{ trans('laravilt-ai::ai.chat.delete_session') }}
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </button>
        </div>
      </ScrollArea>
    </aside>

    <!-- Main Chat Area -->
    <div class="flex flex-1 flex-col bg-background">
      <!-- Header -->
      <header class="flex h-14 items-center justify-between border-b border-border bg-card px-4 shadow-sm">
        <div class="flex items-center gap-3">
          <Button
            v-if="showSidebar"
            variant="ghost"
            size="icon"
            class="text-muted-foreground hover:text-foreground"
            @click="sidebarOpen = !sidebarOpen"
          >
            <PanelLeftClose v-if="sidebarOpen" class="h-5 w-5" />
            <PanelLeft v-else class="h-5 w-5" />
          </Button>
          <div class="flex items-center gap-2.5">
            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-gradient-to-br from-primary to-primary/70 shadow-sm">
              <Sparkles class="h-5 w-5 text-primary-foreground" />
            </div>
            <span class="text-base font-semibold text-foreground">{{ trans('laravilt-ai::ai.chat.title') }}</span>
          </div>
        </div>

        <!-- Model Selector -->
        <div class="flex items-center gap-2">
          <Select v-model="selectedProvider">
            <SelectTrigger class="h-9 w-[140px] border-input bg-background text-sm shadow-sm">
              <SelectValue :placeholder="trans('laravilt-ai::ai.providers.select_provider')">
                {{ currentProviderLabel }}
              </SelectValue>
            </SelectTrigger>
            <SelectContent>
              <SelectItem
                v-for="provider in availableProviders"
                :key="provider.key"
                :value="provider.key"
              >
                {{ provider.label }}
              </SelectItem>
            </SelectContent>
          </Select>

          <Select v-model="selectedModel">
            <SelectTrigger class="h-9 w-[180px] border-input bg-background text-sm shadow-sm">
              <SelectValue :placeholder="trans('laravilt-ai::ai.providers.select_model')">
                {{ currentModelLabel }}
              </SelectValue>
            </SelectTrigger>
            <SelectContent>
              <SelectItem
                v-for="model in availableModels"
                :key="model.key"
                :value="model.key"
              >
                {{ model.label }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>
      </header>

      <!-- Messages Area -->
      <div ref="chatContainerRef" class="flex-1 overflow-y-auto bg-muted/20">
        <!-- Loading Config -->
        <div
          v-if="configLoading"
          class="flex h-full items-center justify-center"
        >
          <div class="flex flex-col items-center gap-3">
            <Loader2 class="h-8 w-8 animate-spin text-primary" />
            <span class="text-sm text-muted-foreground">Loading AI configuration...</span>
          </div>
        </div>

        <!-- Not Configured -->
        <div
          v-else-if="!config?.configured"
          class="flex h-full flex-col items-center justify-center gap-4 p-8 text-center"
        >
          <div class="rounded-full bg-muted p-5 shadow-sm">
            <Settings2 class="h-10 w-10 text-muted-foreground" />
          </div>
          <div>
            <h3 class="text-lg font-semibold text-foreground">{{ trans('laravilt-ai::ai.errors.not_configured') }}</h3>
            <p class="mt-2 max-w-sm text-sm text-muted-foreground">
              Please configure AI providers in your panel settings to enable the AI assistant.
            </p>
          </div>
        </div>

        <!-- Empty State -->
        <div
          v-else-if="messages.length === 0"
          class="flex h-full flex-col items-center justify-center gap-8 p-8"
        >
          <div class="flex h-20 w-20 items-center justify-center rounded-2xl bg-gradient-to-br from-primary to-primary/60 shadow-lg">
            <Sparkles class="h-10 w-10 text-primary-foreground" />
          </div>
          <div class="text-center">
            <h2 class="text-2xl font-bold text-foreground">{{ trans('laravilt-ai::ai.chat.title') }}</h2>
            <p class="mt-3 max-w-lg text-muted-foreground">
              {{ trans('laravilt-ai::ai.chat.type_message') }}
            </p>
          </div>
          <div class="flex flex-wrap items-center justify-center gap-2">
            <Button variant="outline" size="sm" class="gap-2 text-xs" @click="input = 'Help me understand this codebase'">
              <Sparkles class="h-3 w-3" />
              Understand codebase
            </Button>
            <Button variant="outline" size="sm" class="gap-2 text-xs" @click="input = 'Generate a summary report'">
              <Sparkles class="h-3 w-3" />
              Generate report
            </Button>
            <Button variant="outline" size="sm" class="gap-2 text-xs" @click="input = 'Help me debug an issue'">
              <Sparkles class="h-3 w-3" />
              Debug issue
            </Button>
          </div>
        </div>

        <!-- Messages -->
        <div v-else class="mx-auto max-w-3xl space-y-6 px-4 py-6">
          <div
            v-for="(message, index) in messages"
            :key="index"
            :class="[
              'group flex gap-3',
              message.role === 'user' ? 'justify-end' : 'justify-start'
            ]"
          >
            <!-- Assistant Avatar -->
            <div
              v-if="message.role === 'assistant'"
              class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-primary to-primary/70 shadow-sm"
            >
              <Bot class="h-5 w-5 text-primary-foreground" />
            </div>

            <!-- Message Content -->
            <div
              :class="[
                'relative max-w-[80%] rounded-xl px-4 py-3 shadow-sm',
                message.role === 'user'
                  ? 'bg-primary text-primary-foreground'
                  : 'bg-card border border-border'
              ]"
            >
              <div class="whitespace-pre-wrap text-sm leading-relaxed">{{ message.content }}</div>
              <div
                :class="[
                  'mt-2 flex items-center justify-between gap-3 text-xs',
                  message.role === 'user' ? 'text-primary-foreground/60' : 'text-muted-foreground'
                ]"
              >
                <span>{{ formatTime(message.timestamp) }}</span>
                <!-- Copy Button inline -->
                <button
                  :class="[
                    'flex h-6 w-6 items-center justify-center rounded-md opacity-0 transition-all group-hover:opacity-100',
                    message.role === 'user' ? 'hover:bg-primary-foreground/10' : 'hover:bg-muted'
                  ]"
                  @click="copyMessage(message.content, index)"
                >
                  <Check v-if="copiedMessageIndex === index" class="h-3.5 w-3.5 text-green-500" />
                  <Copy v-else class="h-3.5 w-3.5" />
                </button>
              </div>
            </div>

            <!-- User Avatar -->
            <div
              v-if="message.role === 'user'"
              class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-muted shadow-sm"
            >
              <User class="h-5 w-5 text-muted-foreground" />
            </div>
          </div>

          <!-- Typing Indicator -->
          <div v-if="loading && !streaming" class="flex gap-3">
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-primary to-primary/70 shadow-sm">
              <Bot class="h-5 w-5 text-primary-foreground" />
            </div>
            <div class="rounded-xl border border-border bg-card px-4 py-3 shadow-sm">
              <div class="flex items-center gap-1.5">
                <span class="h-2 w-2 animate-bounce rounded-full bg-primary/60" style="animation-delay: 0ms"></span>
                <span class="h-2 w-2 animate-bounce rounded-full bg-primary/60" style="animation-delay: 150ms"></span>
                <span class="h-2 w-2 animate-bounce rounded-full bg-primary/60" style="animation-delay: 300ms"></span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Input Area -->
      <div class="border-t border-border bg-card p-4 shadow-[0_-1px_5px_rgba(0,0,0,0.03)]">
        <div class="mx-auto max-w-3xl">
          <div class="relative flex items-end gap-3 rounded-xl border border-input bg-background p-2 shadow-sm transition-shadow focus-within:shadow-md focus-within:ring-2 focus-within:ring-ring/50">
            <Textarea
              ref="inputRef"
              v-model="input"
              rows="1"
              class="min-h-[44px] flex-1 resize-none border-0 bg-transparent px-3 py-2.5 text-sm placeholder:text-muted-foreground focus-visible:ring-0"
              :placeholder="trans('laravilt-ai::ai.chat.type_message')"
              :disabled="loading || !config?.configured"
              @keydown="handleKeydown"
              @input="autoResize"
            />
            <Button
              size="icon"
              class="h-10 w-10 shrink-0 rounded-lg shadow-sm transition-transform hover:scale-105"
              :disabled="!canSend"
              @click="sendMessage"
            >
              <Loader2 v-if="loading" class="h-5 w-5 animate-spin" />
              <Send v-else class="h-5 w-5" />
            </Button>
          </div>
          <p class="mt-3 text-center text-xs text-muted-foreground">
            Press <kbd class="rounded bg-muted px-1.5 py-0.5 font-mono text-[10px]">Enter</kbd> to send • <kbd class="rounded bg-muted px-1.5 py-0.5 font-mono text-[10px]">Shift+Enter</kbd> for new line
          </p>
        </div>
      </div>
    </div>
  </div>
</template>
