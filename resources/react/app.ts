// Export React components
export { default as GlobalSearch } from './components/GlobalSearch';
export { default as AIChat } from './components/AIChat';

// Export composables
export * from './composables/useAI';
export * from './composables/useGlobalSearch';

/**
 * Ai plugin entry (React twin of `resources/js/app.js`).
 *
 * The Vue plugin registers no global `laravilt-*` components and only logs on install,
 * so `register()` does the same.
 */
export default {
    register(options: Record<string, any> = {}): void {
        // Plugin installation logic
        console.log('Ai plugin installed', options);
    },
};
