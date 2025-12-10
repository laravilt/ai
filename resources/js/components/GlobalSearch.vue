<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { Search, Loader2, X, Sparkles, ArrowRight } from 'lucide-vue-next'

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
    useAI?: boolean
    endpoint?: string
  }>(),
  {
    placeholder: 'Search...',
    useAI: true,
    endpoint: '/laravilt-ai/search',
  }
)

const emit = defineEmits<{
  (e: 'select', result: SearchResult, group: SearchGroup): void
  (e: 'close'): void
}>()

const isOpen = ref(false)
const query = ref('')
const loading = ref(false)
const results = ref<SearchGroup[]>([])
const selectedIndex = ref(0)
const inputRef = ref<HTMLInputElement | null>(null)

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
  }, 300)
})

async function performSearch(searchQuery: string) {
  loading.value = true
  selectedIndex.value = 0

  try {
    const response = await fetch(
      `${props.endpoint}?query=${encodeURIComponent(searchQuery)}&useAI=${props.useAI}`
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

defineExpose({ open, close })
</script>

<template>
  <!-- Trigger Button -->
  <button
    type="button"
    class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-500 shadow-sm transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700"
    @click="open"
  >
    <Search class="h-4 w-4" />
    <span class="hidden sm:inline">Search...</span>
    <kbd
      class="ml-2 hidden rounded bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-500 dark:bg-gray-700 dark:text-gray-400 sm:inline"
    >
      ⌘K
    </kbd>
  </button>

  <!-- Spotlight Modal -->
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
        class="fixed inset-0 z-50 overflow-y-auto p-4 sm:p-6 md:p-20"
        @click.self="close"
      >
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-gray-500/75 transition-opacity dark:bg-gray-900/80" />

        <!-- Modal -->
        <Transition
          enter-active-class="duration-200 ease-out"
          enter-from-class="opacity-0 scale-95"
          enter-to-class="opacity-100 scale-100"
          leave-active-class="duration-150 ease-in"
          leave-from-class="opacity-100 scale-100"
          leave-to-class="opacity-0 scale-95"
        >
          <div
            v-if="isOpen"
            class="relative mx-auto max-w-xl transform divide-y divide-gray-100 overflow-hidden rounded-xl bg-white shadow-2xl ring-1 ring-black/5 transition-all dark:divide-gray-700 dark:bg-gray-800 dark:ring-white/10"
          >
            <!-- Search Input -->
            <div class="relative">
              <Search
                class="pointer-events-none absolute left-4 top-3.5 h-5 w-5 text-gray-400"
              />
              <input
                ref="inputRef"
                v-model="query"
                type="text"
                class="h-12 w-full border-0 bg-transparent pl-11 pr-12 text-gray-900 placeholder:text-gray-400 focus:ring-0 dark:text-gray-100 sm:text-sm"
                :placeholder="placeholder"
                @keydown="handleKeydown"
              />
              <div class="absolute right-3 top-3 flex items-center gap-2">
                <Loader2 v-if="loading" class="h-5 w-5 animate-spin text-gray-400" />
                <button
                  v-else-if="query"
                  type="button"
                  class="rounded p-0.5 text-gray-400 hover:bg-gray-100 hover:text-gray-500 dark:hover:bg-gray-700"
                  @click="query = ''"
                >
                  <X class="h-4 w-4" />
                </button>
              </div>
            </div>

            <!-- AI Badge -->
            <div
              v-if="useAI"
              class="flex items-center gap-1.5 border-b border-gray-100 px-4 py-2 text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400"
            >
              <Sparkles class="h-3.5 w-3.5 text-purple-500" />
              <span>AI-powered search</span>
            </div>

            <!-- Results -->
            <div v-if="results.length > 0" class="max-h-96 scroll-py-3 overflow-y-auto p-3">
              <div v-for="group in results" :key="group.resource" class="mb-4 last:mb-0">
                <h3
                  class="mb-2 flex items-center gap-2 px-3 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400"
                >
                  {{ group.label }}
                </h3>
                <ul class="space-y-1">
                  <li v-for="(result, idx) in group.results" :key="result.id">
                    <button
                      type="button"
                      class="group flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left transition"
                      :class="[
                        flatResults.findIndex((f) => f.result === result) === selectedIndex
                          ? 'bg-primary-50 text-primary-900 dark:bg-primary-900/20 dark:text-primary-100'
                          : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700',
                      ]"
                      @click="selectResult(result, group)"
                      @mouseenter="
                        selectedIndex = flatResults.findIndex((f) => f.result === result)
                      "
                    >
                      <div class="flex-1 truncate">
                        <div class="font-medium">{{ result.title }}</div>
                        <div
                          v-if="result.subtitle"
                          class="truncate text-sm text-gray-500 dark:text-gray-400"
                        >
                          {{ result.subtitle }}
                        </div>
                      </div>
                      <ArrowRight
                        class="h-4 w-4 flex-shrink-0 text-gray-400 opacity-0 transition group-hover:opacity-100"
                      />
                    </button>
                  </li>
                </ul>
              </div>
            </div>

            <!-- Empty State -->
            <div
              v-else-if="query && !loading"
              class="px-6 py-14 text-center text-sm text-gray-500 dark:text-gray-400"
            >
              <Search class="mx-auto mb-4 h-10 w-10 text-gray-300 dark:text-gray-600" />
              <p>No results found for "{{ query }}"</p>
              <p class="mt-1 text-xs">Try searching with different keywords</p>
            </div>

            <!-- Initial State -->
            <div
              v-else-if="!query"
              class="px-6 py-14 text-center text-sm text-gray-500 dark:text-gray-400"
            >
              <Search class="mx-auto mb-4 h-10 w-10 text-gray-300 dark:text-gray-600" />
              <p>Start typing to search</p>
              <div class="mt-4 flex justify-center gap-2">
                <kbd
                  class="rounded bg-gray-100 px-2 py-1 text-xs font-medium dark:bg-gray-700"
                  >↑↓</kbd
                >
                <span class="text-xs">to navigate</span>
                <kbd
                  class="rounded bg-gray-100 px-2 py-1 text-xs font-medium dark:bg-gray-700"
                  >↵</kbd
                >
                <span class="text-xs">to select</span>
                <kbd
                  class="rounded bg-gray-100 px-2 py-1 text-xs font-medium dark:bg-gray-700"
                  >esc</kbd
                >
                <span class="text-xs">to close</span>
              </div>
            </div>

            <!-- Footer -->
            <div
              class="flex items-center justify-between border-t border-gray-100 px-4 py-2.5 text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400"
            >
              <span v-if="totalResults > 0">{{ totalResults }} results</span>
              <span v-else>Type to search</span>
              <div class="flex items-center gap-3">
                <span>Press</span>
                <kbd
                  class="rounded bg-gray-100 px-1.5 py-0.5 font-medium dark:bg-gray-700"
                  >ESC</kbd
                >
                <span>to close</span>
              </div>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>
