<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { Search, Loader2, X, Sparkles, ArrowRight, Command, FileText, Users, Package, Settings, Database } from 'lucide-vue-next'
import { usePage } from '@inertiajs/vue3'
import * as LucideIcons from 'lucide-vue-next'
import { useLocalization } from '@laravilt/support/composables'

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

const props = withDefaults(
  defineProps<{
    placeholder?: string
  }>(),
  {
    placeholder: undefined,
  }
)

const emit = defineEmits<{
  (e: 'select', result: SearchResult, group: SearchGroup): void
  (e: 'close'): void
}>()

const { trans } = useLocalization()

const page = usePage<{
  panel?: {
    hasGlobalSearch?: boolean
    globalSearchEndpoint?: string
    globalSearchConfig?: {
      enabled?: boolean
      useAI?: boolean
      debounce?: number
    }
    hasAI?: boolean
  }
}>()

const isOpen = ref(false)
const query = ref('')
const loading = ref(false)
const results = ref<SearchGroup[]>([])
const selectedIndex = ref(0)
const inputRef = ref<HTMLInputElement | null>(null)
const useAI = ref(false)

// Initialize AI mode from config
onMounted(() => {
  useAI.value = page.props?.panel?.globalSearchConfig?.useAI ?? false
})

const hasGlobalSearch = computed(() => page.props?.panel?.hasGlobalSearch ?? true)
const hasAIConfig = computed(() => page.props?.panel?.hasAI ?? false)
const endpoint = computed(() => page.props?.panel?.globalSearchEndpoint ?? '/global-search')
const debounceMs = computed(() => page.props?.panel?.globalSearchConfig?.debounce ?? 300)

// Translation helper with proper keys from laravilt-ai::ai.search.*
const t = computed(() => ({
  placeholder: props.placeholder ?? trans('laravilt-ai::ai.search.placeholder'),
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
}))

// Flatten results for keyboard navigation
const flatResults = computed(() => {
  const flat: { result: SearchResult; group: SearchGroup; index: number }[] = []
  let index = 0
  for (const group of results.value) {
    for (const result of group.results) {
      flat.push({ result, group, index })
      index++
    }
  }
  return flat
})

const totalResults = computed(() => flatResults.value.length)

// Search debounce
let searchTimeout: ReturnType<typeof setTimeout> | null = null

watch(query, (newQuery) => {
  if (searchTimeout) {
    clearTimeout(searchTimeout)
  }

  if (!newQuery.trim()) {
    results.value = []
    return
  }

  searchTimeout = setTimeout(async () => {
    await performSearch(newQuery)
  }, debounceMs.value)
})

async function performSearch(searchQuery: string) {
  loading.value = true
  selectedIndex.value = 0

  try {
    const response = await fetch(
      `${endpoint.value}?query=${encodeURIComponent(searchQuery)}&useAI=${useAI.value}`
    )
    const data = await response.json()
    results.value = data.results || []
  } catch (error) {
    console.error('Search error:', error)
    results.value = []
  } finally {
    loading.value = false
  }
}

function toggleAI() {
  useAI.value = !useAI.value
  // Re-search if there's a query
  if (query.value.trim()) {
    performSearch(query.value)
  }
}

function open() {
  isOpen.value = true
  setTimeout(() => {
    inputRef.value?.focus()
  }, 100)
}

function close() {
  isOpen.value = false
  query.value = ''
  results.value = []
  selectedIndex.value = 0
  emit('close')
}

function selectResult(result: SearchResult, group: SearchGroup) {
  emit('select', result, group)
  if (result.url) {
    window.location.href = result.url
  }
  close()
}

function handleKeydown(event: KeyboardEvent) {
  if (!isOpen.value) return

  switch (event.key) {
    case 'ArrowDown':
      event.preventDefault()
      selectedIndex.value = Math.min(selectedIndex.value + 1, totalResults.value - 1)
      break
    case 'ArrowUp':
      event.preventDefault()
      selectedIndex.value = Math.max(selectedIndex.value - 1, 0)
      break
    case 'Enter':
      event.preventDefault()
      const selected = flatResults.value[selectedIndex.value]
      if (selected) {
        selectResult(selected.result, selected.group)
      }
      break
    case 'Escape':
      event.preventDefault()
      close()
      break
  }
}

// Global keyboard shortcut (Cmd/Ctrl + K) and ESC
function handleGlobalKeydown(event: KeyboardEvent) {
  // Handle Cmd/Ctrl + K
  if ((event.metaKey || event.ctrlKey) && event.key === 'k') {
    event.preventDefault()
    if (isOpen.value) {
      close()
    } else {
      open()
    }
    return
  }

  // Handle ESC globally (even when input is not focused)
  if (event.key === 'Escape' && isOpen.value) {
    event.preventDefault()
    event.stopPropagation()
    close()
  }
}

onMounted(() => {
  document.addEventListener('keydown', handleGlobalKeydown, true)
})

onUnmounted(() => {
  document.removeEventListener('keydown', handleGlobalKeydown, true)
})

// Map of resource icons with fallback
const resourceIconMap: Record<string, string> = {
  'users': 'Users',
  'products': 'Package',
  'settings': 'Settings',
  'documents': 'FileText',
  'files': 'FileText',
}

// Get icon component for a group
function getIconComponent(iconName?: string): any {
  if (!iconName) return Database

  // Try direct name first
  if ((LucideIcons as any)[iconName]) {
    return (LucideIcons as any)[iconName]
  }

  // Convert kebab-case to PascalCase
  const pascalCase = iconName
    .split('-')
    .map(word => word.charAt(0).toUpperCase() + word.slice(1))
    .join('')

  if ((LucideIcons as any)[pascalCase]) {
    return (LucideIcons as any)[pascalCase]
  }

  // Try without heroicon prefix
  const withoutPrefix = iconName.replace(/^heroicon-[os]-/, '')
  const cleanPascal = withoutPrefix
    .split('-')
    .map(word => word.charAt(0).toUpperCase() + word.slice(1))
    .join('')

  if ((LucideIcons as any)[cleanPascal]) {
    return (LucideIcons as any)[cleanPascal]
  }

  return Database
}

// Get icon color class for a group (for variety)
function getIconColorClass(index: number): string {
  const colors = [
    'bg-blue-500/10 text-blue-500 dark:bg-blue-500/20 dark:text-blue-400',
    'bg-purple-500/10 text-purple-500 dark:bg-purple-500/20 dark:text-purple-400',
    'bg-green-500/10 text-green-500 dark:bg-green-500/20 dark:text-green-400',
    'bg-orange-500/10 text-orange-500 dark:bg-orange-500/20 dark:text-orange-400',
    'bg-pink-500/10 text-pink-500 dark:bg-pink-500/20 dark:text-pink-400',
    'bg-cyan-500/10 text-cyan-500 dark:bg-cyan-500/20 dark:text-cyan-400',
  ]
  return colors[index % colors.length]
}

defineExpose({ open, close })
</script>

<template>
  <!-- Trigger Button - shadcn style -->
  <button
    v-if="hasGlobalSearch"
    type="button"
    class="inline-flex items-center justify-center gap-2 rounded-lg border border-input bg-background px-3 py-1.5 text-sm text-muted-foreground shadow-sm transition hover:bg-accent hover:text-accent-foreground"
    :title="t.placeholder"
    @click="open"
  >
    <Search class="h-4 w-4" />
    <kbd
      class="pointer-events-none hidden h-5 select-none items-center gap-0.5 rounded border border-input bg-muted px-1.5 font-mono text-[10px] font-medium sm:flex"
    >
      <Command class="h-2.5 w-2.5" />K
    </kbd>
  </button>

  <!-- Spotlight Modal - shadcn style -->
  <Teleport to="body">
    <Transition
      enter-active-class="duration-200 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="isOpen"
        class="fixed inset-0 z-50 overflow-y-auto p-4 pt-[15vh] sm:p-6 sm:pt-[20vh]"
        @click.self="close"
      >
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-black/50 backdrop-blur-sm transition-opacity" />

        <!-- Modal -->
        <Transition
          enter-active-class="duration-200 ease-out"
          enter-from-class="opacity-0 scale-95 translate-y-4"
          enter-to-class="opacity-100 scale-100 translate-y-0"
          leave-active-class="duration-150 ease-in"
          leave-from-class="opacity-100 scale-100 translate-y-0"
          leave-to-class="opacity-0 scale-95 translate-y-4"
        >
          <div
            v-if="isOpen"
            class="relative mx-auto max-w-2xl transform overflow-hidden rounded-xl bg-popover text-popover-foreground shadow-2xl ring-1 ring-border transition-all"
          >
            <!-- Search Input - shadcn style -->
            <div class="relative flex items-center px-4 py-3">
              <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-muted">
                <Search class="h-5 w-5 text-muted-foreground" />
              </div>
              <input
                ref="inputRef"
                v-model="query"
                type="text"
                class="h-12 flex-1 border-0 bg-transparent px-4 text-lg text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-0"
                :placeholder="t.placeholder"
                @keydown="handleKeydown"
              />
              <div class="flex items-center gap-2">
                <!-- AI Toggle Button -->
                <button
                  v-if="hasAIConfig"
                  type="button"
                  class="flex h-9 w-9 items-center justify-center rounded-lg transition-all duration-200"
                  :class="[
                    useAI
                      ? 'bg-primary text-primary-foreground shadow-sm'
                      : 'bg-muted text-muted-foreground hover:bg-accent hover:text-accent-foreground',
                  ]"
                  :title="useAI ? t.ai_enabled : t.ai_disabled"
                  @click="toggleAI"
                >
                  <Sparkles class="h-4 w-4" />
                </button>
                <Loader2 v-if="loading" class="h-5 w-5 animate-spin text-muted-foreground" />
                <button
                  v-else-if="query"
                  type="button"
                  class="flex h-8 w-8 items-center justify-center rounded-lg bg-muted text-muted-foreground transition hover:bg-accent hover:text-accent-foreground"
                  @click="query = ''"
                >
                  <X class="h-4 w-4" />
                </button>
              </div>
            </div>

            <!-- AI Badge -->
            <div
              v-if="useAI && hasAIConfig"
              class="mx-4 mb-3 inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-3 py-1 text-xs font-medium text-primary"
            >
              <Sparkles class="h-3 w-3" />
              <span>{{ t.ai_powered }}</span>
            </div>

            <!-- Divider -->
            <div v-if="results.length > 0 || query" class="mx-4 h-px bg-border" />

            <!-- Results - shadcn style -->
            <div v-if="results.length > 0" class="max-h-[50vh] overflow-y-auto overscroll-contain px-3 py-3">
              <div v-for="(group, groupIndex) in results" :key="group.resource" class="mb-4 last:mb-0">
                <!-- Group Header -->
                <h3
                  class="mb-2 flex items-center gap-2 px-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground"
                >
                  <component :is="getIconComponent(group.icon)" class="h-3.5 w-3.5" />
                  {{ group.label }}
                </h3>

                <!-- Results List -->
                <ul class="space-y-1">
                  <li v-for="(result, idx) in group.results" :key="result.id">
                    <button
                      type="button"
                      class="group flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left transition-all duration-150"
                      :class="[
                        flatResults.findIndex((f) => f.result === result) === selectedIndex
                          ? 'bg-primary text-primary-foreground'
                          : 'text-foreground hover:bg-accent',
                      ]"
                      @click="selectResult(result, group)"
                      @mouseenter="selectedIndex = flatResults.findIndex((f) => f.result === result)"
                    >
                      <!-- Icon with color background -->
                      <div
                        class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg transition-colors"
                        :class="[
                          flatResults.findIndex((f) => f.result === result) === selectedIndex
                            ? 'bg-primary-foreground/20'
                            : 'bg-muted',
                        ]"
                      >
                        <component
                          :is="getIconComponent(group.icon)"
                          class="h-5 w-5"
                          :class="[
                            flatResults.findIndex((f) => f.result === result) === selectedIndex
                              ? 'text-primary-foreground'
                              : 'text-muted-foreground',
                          ]"
                        />
                      </div>

                      <!-- Content -->
                      <div class="flex-1 min-w-0">
                        <div class="truncate font-medium">{{ result.title }}</div>
                        <div
                          v-if="result.subtitle"
                          class="truncate text-sm"
                          :class="[
                            flatResults.findIndex((f) => f.result === result) === selectedIndex
                              ? 'text-primary-foreground/70'
                              : 'text-muted-foreground',
                          ]"
                        >
                          {{ result.subtitle }}
                        </div>
                      </div>

                      <!-- Arrow indicator -->
                      <div
                        class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-md transition-all"
                        :class="[
                          flatResults.findIndex((f) => f.result === result) === selectedIndex
                            ? 'bg-primary-foreground/20 text-primary-foreground'
                            : 'bg-muted text-muted-foreground opacity-0 group-hover:opacity-100',
                        ]"
                      >
                        <ArrowRight class="h-4 w-4" />
                      </div>
                    </button>
                  </li>
                </ul>
              </div>
            </div>

            <!-- Empty State - shadcn style -->
            <div
              v-else-if="query && !loading"
              class="px-6 py-14 text-center"
            >
              <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-xl bg-muted">
                <Search class="h-8 w-8 text-muted-foreground/50" />
              </div>
              <p class="text-lg font-medium text-foreground">{{ t.no_results }}</p>
              <p class="mt-1 text-sm text-muted-foreground">
                {{ trans('laravilt-ai::ai.search.no_results_for', { query }) }}
              </p>
            </div>

            <!-- Initial State - shadcn style -->
            <div
              v-else-if="!query"
              class="px-6 py-14 text-center"
            >
              <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-xl bg-muted">
                <Search class="h-8 w-8 text-muted-foreground/50" />
              </div>
              <p class="text-lg font-medium text-foreground">{{ t.start_typing }}</p>
              <p class="mt-1 text-sm text-muted-foreground">{{ t.type_to_search }}</p>
            </div>

            <!-- Footer - shadcn style -->
            <div
              class="flex items-center justify-between border-t border-border bg-muted/50 px-4 py-2.5"
            >
              <span class="text-xs text-muted-foreground">
                <template v-if="totalResults > 0">{{ trans('laravilt-ai::ai.search.results_count', { count: totalResults }) }}</template>
                <template v-else>{{ t.type_to_search }}</template>
              </span>
              <div class="flex items-center gap-3 text-xs text-muted-foreground">
                <div class="flex items-center gap-1">
                  <kbd class="flex h-5 items-center justify-center rounded border border-input bg-background px-1.5 font-mono text-[10px] font-medium">↑</kbd>
                  <kbd class="flex h-5 items-center justify-center rounded border border-input bg-background px-1.5 font-mono text-[10px] font-medium">↓</kbd>
                  <span class="ms-1">{{ t.to_navigate }}</span>
                </div>
                <div class="flex items-center gap-1">
                  <kbd class="flex h-5 items-center justify-center rounded border border-input bg-background px-1.5 font-mono text-[10px] font-medium">↵</kbd>
                  <span class="ms-1">{{ t.to_select }}</span>
                </div>
                <div class="flex items-center gap-1">
                  <kbd class="flex h-5 items-center justify-center rounded border border-input bg-background px-2 font-mono text-[10px] font-medium">esc</kbd>
                  <span class="ms-1">{{ t.to_close }}</span>
                </div>
              </div>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>
