<script setup lang="ts">
import { ref, computed, watch, nextTick, onMounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import MarkdownIt from 'markdown-it'
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
  Database,
  AtSign,
} from 'lucide-vue-next'

// Initialize markdown parser
const md = new MarkdownIt({
  html: false,
  linkify: true,
  typographer: true,
  breaks: true,
})
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

interface Resource {
  slug: string
  label: string
  singular: string
  count: number
  fields: string[]
}

interface MentionedResource {
  slug: string
  label: string
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

// Resource mentions state
const resources = ref<Resource[]>([])
const showMentionDropdown = ref(false)
const mentionQuery = ref('')
const mentionStartIndex = ref(-1)
const selectedMentionIndex = ref(0)
const mentionedResources = ref<MentionedResource[]>([])
const mentionDropdownRef = ref<HTMLElement | null>(null)

const chatContainerRef = ref<HTMLElement | null>(null)
const inputRef = ref<{ focus: () => void; el: { value: HTMLTextAreaElement | null } } | null>(null)
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

// Filtered resources for mention dropdown
const filteredResources = computed(() => {
  if (!mentionQuery.value) return resources.value
  const query = mentionQuery.value.toLowerCase()
  return resources.value.filter(
    (r) =>
      r.label.toLowerCase().includes(query) ||
      r.slug.toLowerCase().includes(query) ||
      r.singular.toLowerCase().includes(query)
  )
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

// Load available resources for @ mentions
async function loadResources() {
  try {
    const response = await fetch(`${props.endpoint}/resources`, {
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrfToken.value,
      },
      credentials: 'same-origin',
    })
    const data = await response.json()
    resources.value = data.resources || []
  } catch (error) {
    console.error('Failed to load resources:', error)
  }
}

// Create new session
async function createSession() {
  currentSession.value = null
  messages.value = []
  input.value = ''
  clearMentions()
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
  if (inputRef.value?.el?.value) {
    inputRef.value.el.value.style.height = 'auto'
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

  // Create session if this is a new conversation
  let sessionId = currentSession.value?.id
  if (!sessionId && messages.value.length === 0) {
    try {
      const sessionResponse = await fetch(`${props.endpoint}/sessions`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken.value,
        },
        credentials: 'same-origin',
        body: JSON.stringify({
          title: userMessage.content.substring(0, 50) + (userMessage.content.length > 50 ? '...' : ''),
          provider: selectedProvider.value,
          model: selectedModel.value,
        }),
      })
      const sessionData = await sessionResponse.json()
      if (sessionData.session) {
        currentSession.value = sessionData.session
        sessionId = sessionData.session.id
      }
    } catch (error) {
      console.error('Failed to create session:', error)
    }
  }

  messages.value.push(userMessage)
  input.value = ''
  loading.value = true
  streaming.value = true

  // Reset textarea height safely
  await nextTick()
  const textareaEl = inputRef.value?.el?.value
  if (textareaEl) {
    textareaEl.style.height = 'auto'
  }

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
        session_id: sessionId,
        mentioned_resources: mentionedResources.value.map(r => r.slug),
      }),
    })

    // Check if response is ok
    if (!response.ok) {
      const errorText = await response.text()
      console.error('Stream response error:', response.status, errorText)
      throw new Error(`HTTP ${response.status}: ${errorText}`)
    }

    const reader = response.body?.getReader()
    const decoder = new TextDecoder()

    if (reader) {
      let buffer = ''
      while (true) {
        const { done, value } = await reader.read()
        if (done) break

        buffer += decoder.decode(value, { stream: true })
        const lines = buffer.split('\n')

        // Keep the last incomplete line in buffer
        buffer = lines.pop() || ''

        for (const line of lines) {
          const trimmedLine = line.trim()
          if (trimmedLine.startsWith('data: ')) {
            const data = trimmedLine.slice(6)
            if (data === '[DONE]') continue

            try {
              const json = JSON.parse(data)
              if (json.error) {
                console.error('AI error:', json.error)
                assistantMessage.content = `Error: ${json.error}`
              } else if (json.content) {
                assistantMessage.content += json.content
                await nextTick()
                scrollToBottom()
              }
            } catch (parseError) {
              // Ignore parsing errors for incomplete chunks
              console.debug('Parse error (may be incomplete):', data)
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

    // Clear mentioned resources after sending
    clearMentions()

    // Refresh sessions list to show the new/updated session
    if (props.showSidebar) {
      await loadSessions()
    }

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
  // Handle mention dropdown navigation
  if (showMentionDropdown.value && filteredResources.value.length > 0) {
    if (event.key === 'ArrowDown') {
      event.preventDefault()
      selectedMentionIndex.value = Math.min(
        selectedMentionIndex.value + 1,
        filteredResources.value.length - 1
      )
      return
    }
    if (event.key === 'ArrowUp') {
      event.preventDefault()
      selectedMentionIndex.value = Math.max(selectedMentionIndex.value - 1, 0)
      return
    }
    if (event.key === 'Enter' || event.key === 'Tab') {
      event.preventDefault()
      selectMention(filteredResources.value[selectedMentionIndex.value])
      return
    }
    if (event.key === 'Escape') {
      event.preventDefault()
      showMentionDropdown.value = false
      return
    }
  }

  // Normal enter to send
  if (event.key === 'Enter' && !event.shiftKey) {
    event.preventDefault()
    streamMessage()
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

// Handle input for @ mention detection
function handleInput(event: Event) {
  autoResize(event)

  const textarea = event.target as HTMLTextAreaElement
  const cursorPos = textarea.selectionStart
  const textBeforeCursor = input.value.substring(0, cursorPos)

  // Find the last @ symbol before cursor
  const lastAtIndex = textBeforeCursor.lastIndexOf('@')

  if (lastAtIndex !== -1) {
    // Check if @ is at start or preceded by whitespace
    const charBefore = lastAtIndex > 0 ? textBeforeCursor[lastAtIndex - 1] : ' '
    if (charBefore === ' ' || charBefore === '\n' || lastAtIndex === 0) {
      const textAfterAt = textBeforeCursor.substring(lastAtIndex + 1)
      // Only show dropdown if there's no space after @
      if (!textAfterAt.includes(' ') && !textAfterAt.includes('\n')) {
        mentionStartIndex.value = lastAtIndex
        mentionQuery.value = textAfterAt
        showMentionDropdown.value = true
        selectedMentionIndex.value = 0
        return
      }
    }
  }

  // Hide dropdown if no valid @ mention
  showMentionDropdown.value = false
  mentionQuery.value = ''
  mentionStartIndex.value = -1
}

// Select a resource from the mention dropdown
function selectMention(resource: Resource) {
  const textarea = inputRef.value?.el?.value
  if (!textarea) return

  const beforeMention = input.value.substring(0, mentionStartIndex.value)
  const afterCursor = input.value.substring(textarea.selectionStart)

  // Insert the mention with a visual marker
  const mentionText = `@${resource.label}`
  input.value = beforeMention + mentionText + ' ' + afterCursor

  // Add to mentioned resources if not already there
  if (!mentionedResources.value.find(r => r.slug === resource.slug)) {
    mentionedResources.value.push({
      slug: resource.slug,
      label: resource.label,
    })
  }

  // Hide dropdown and reset
  showMentionDropdown.value = false
  mentionQuery.value = ''
  mentionStartIndex.value = -1

  // Focus back on textarea
  nextTick(() => {
    inputRef.value?.focus()
  })
}

// Remove a mentioned resource
function removeMention(slug: string) {
  mentionedResources.value = mentionedResources.value.filter(r => r.slug !== slug)
}

// Clear all mentions when creating new session
function clearMentions() {
  mentionedResources.value = []
}

function formatTime(timestamp: number | undefined): string {
  if (!timestamp) return ''
  return new Date(timestamp).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
}

function renderMarkdown(content: string): string {
  if (!content) return ''
  return md.render(content)
}

onMounted(async () => {
  await loadConfig()
  await loadResources()
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
  <div class="flex h-full overflow-hidden rounded-xl border border-border bg-background shadow-sm">
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
      <header class="flex h-14 items-center justify-between border-b border-border bg-card/50 px-4 backdrop-blur-sm">
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
            <Button variant="outline" size="sm" class="gap-2 text-xs" @click="input = trans('laravilt-ai::ai.chat.understand_codebase')">
              <Sparkles class="h-3 w-3" />
              {{ trans('laravilt-ai::ai.chat.understand_codebase') }}
            </Button>
            <Button variant="outline" size="sm" class="gap-2 text-xs" @click="input = trans('laravilt-ai::ai.chat.generate_report')">
              <Sparkles class="h-3 w-3" />
              {{ trans('laravilt-ai::ai.chat.generate_report') }}
            </Button>
            <Button variant="outline" size="sm" class="gap-2 text-xs" @click="input = trans('laravilt-ai::ai.chat.debug_issue')">
              <Sparkles class="h-3 w-3" />
              {{ trans('laravilt-ai::ai.chat.debug_issue') }}
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
              <!-- User messages: plain text with auto direction -->
              <div
                v-if="message.role === 'user'"
                dir="auto"
                class="whitespace-pre-wrap text-sm leading-relaxed text-start"
              >{{ message.content }}</div>
              <!-- Assistant messages: markdown with auto direction -->
              <div
                v-else
                dir="auto"
                class="prose prose-sm dark:prose-invert max-w-none text-sm leading-relaxed text-start prose-p:text-start prose-li:text-start prose-pre:bg-muted prose-pre:border prose-pre:border-border prose-pre:rounded-lg prose-pre:text-start prose-code:text-primary prose-code:bg-muted prose-code:px-1.5 prose-code:py-0.5 prose-code:rounded prose-code:before:content-[''] prose-code:after:content-['']"
                v-html="renderMarkdown(message.content)"
              ></div>
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
      <div class="border-t border-border bg-card/50 p-4 backdrop-blur-sm">
        <div class="mx-auto max-w-3xl">
          <!-- Mentioned Resources Chips -->
          <div v-if="mentionedResources.length > 0" class="mb-3 flex flex-wrap gap-2">
            <div
              v-for="resource in mentionedResources"
              :key="resource.slug"
              class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-3 py-1 text-xs font-medium text-primary"
            >
              <Database class="h-3 w-3" />
              <span>{{ resource.label }}</span>
              <button
                type="button"
                class="ml-1 rounded-full p-0.5 hover:bg-primary/20"
                @click="removeMention(resource.slug)"
              >
                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
          </div>

          <div class="relative">
            <!-- Mention Dropdown -->
            <div
              v-if="showMentionDropdown && filteredResources.length > 0"
              ref="mentionDropdownRef"
              class="absolute bottom-full left-0 z-50 mb-2 w-72 overflow-hidden rounded-xl border border-border bg-popover shadow-lg"
            >
              <div class="px-3 py-2 text-xs font-medium text-muted-foreground border-b border-border">
                <div class="flex items-center gap-2">
                  <AtSign class="h-3.5 w-3.5" />
                  <span>{{ trans('laravilt-ai::ai.chat.mention_resource') || 'Mention a resource' }}</span>
                </div>
              </div>
              <ScrollArea class="max-h-64">
                <div class="p-1">
                  <button
                    v-for="(resource, index) in filteredResources"
                    :key="resource.slug"
                    type="button"
                    :class="[
                      'flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm transition-colors',
                      index === selectedMentionIndex
                        ? 'bg-primary text-primary-foreground'
                        : 'text-foreground hover:bg-accent'
                    ]"
                    @click="selectMention(resource)"
                    @mouseenter="selectedMentionIndex = index"
                  >
                    <div
                      :class="[
                        'flex h-8 w-8 items-center justify-center rounded-lg',
                        index === selectedMentionIndex ? 'bg-primary-foreground/20' : 'bg-muted'
                      ]"
                    >
                      <Database
                        :class="[
                          'h-4 w-4',
                          index === selectedMentionIndex ? 'text-primary-foreground' : 'text-muted-foreground'
                        ]"
                      />
                    </div>
                    <div class="flex-1 text-start">
                      <div class="font-medium">{{ resource.label }}</div>
                      <div
                        :class="[
                          'text-xs',
                          index === selectedMentionIndex ? 'text-primary-foreground/70' : 'text-muted-foreground'
                        ]"
                      >
                        {{ resource.count }} {{ resource.count === 1 ? 'record' : 'records' }}
                      </div>
                    </div>
                  </button>
                </div>
              </ScrollArea>
              <div class="border-t border-border bg-muted/50 px-3 py-2 text-xs text-muted-foreground">
                <kbd class="rounded bg-background px-1.5 py-0.5 font-mono text-[10px]">↑↓</kbd> navigate •
                <kbd class="rounded bg-background px-1.5 py-0.5 font-mono text-[10px]">Enter</kbd> select •
                <kbd class="rounded bg-background px-1.5 py-0.5 font-mono text-[10px]">Esc</kbd> close
              </div>
            </div>

            <div class="flex items-end gap-3 rounded-2xl border border-input bg-background p-3 shadow-sm transition-all duration-200 focus-within:shadow-md focus-within:ring-2 focus-within:ring-primary/20 focus-within:border-primary/50">
              <Textarea
                ref="inputRef"
                v-model="input"
                rows="1"
                class="min-h-[44px] flex-1 resize-none border-0 bg-transparent px-3 py-2.5 text-sm placeholder:text-muted-foreground focus-visible:ring-0"
                :placeholder="trans('laravilt-ai::ai.chat.type_message')"
                :disabled="loading || !config?.configured"
                @keydown="handleKeydown"
                @input="handleInput"
              />
              <Button
                size="icon"
                class="h-10 w-10 shrink-0 rounded-xl shadow-sm transition-all duration-200 hover:scale-105 hover:shadow-md"
                :disabled="!canSend"
                @click="streamMessage"
              >
                <Loader2 v-if="loading" class="h-5 w-5 animate-spin" />
                <Send v-else class="h-5 w-5" />
              </Button>
            </div>
          </div>
          <p class="mt-3 text-center text-xs text-muted-foreground">
            {{ trans('laravilt-ai::ai.chat.press_enter') }} <kbd class="rounded bg-muted px-1.5 py-0.5 font-mono text-[10px]">Enter</kbd> {{ trans('laravilt-ai::ai.chat.to_send') }} •
            <kbd class="rounded bg-muted px-1.5 py-0.5 font-mono text-[10px]">Shift+Enter</kbd> {{ trans('laravilt-ai::ai.chat.for_new_line') }} •
            <kbd class="rounded bg-muted px-1.5 py-0.5 font-mono text-[10px]">@</kbd> {{ trans('laravilt-ai::ai.chat.to_mention') }}
          </p>
        </div>
      </div>
    </div>
  </div>
</template>
