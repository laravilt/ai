<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed, markRaw } from 'vue';
import PanelLayout from '@laravilt/panel/layouts/PanelLayout.vue';
import AIChat from '../../components/AIChat.vue';
import { useLocalization } from '@laravilt/support/composables';

const PanelLayoutRaw = markRaw(PanelLayout);
const { trans } = useLocalization();

interface BreadcrumbItem {
    label: string;
    url: string | null;
}

interface AIConfig {
    configured: boolean;
    default: string;
    providers: Record<string, any>;
}

const props = defineProps<{
    breadcrumbs?: BreadcrumbItem[];
    aiConfig?: AIConfig;
    hasAI?: boolean;
}>();

const page = usePage<{ panel?: { path: string } }>();

// Get the endpoint based on the panel path
const endpoint = computed(() => {
    const panelPath = page.props?.panel?.path || 'admin';
    return `/${panelPath}/ai`;
});

// Transform breadcrumbs to frontend format
const transformedBreadcrumbs = computed(() => {
    if (!props.breadcrumbs) return [];
    return props.breadcrumbs.map(item => ({
        title: item.label,
        href: item.url || '#',
    }));
});

const pageTitle = computed(() => trans('laravilt-ai::ai.chat.title'));
</script>

<template>
    <Head :title="pageTitle" />

    <PanelLayoutRaw :breadcrumbs="transformedBreadcrumbs">
        <div class="flex flex-1 flex-col p-4">
            <div class="h-[calc(100vh-12rem)]">
                <AIChat
                    :show-sidebar="true"
                    :endpoint="endpoint"
                />
            </div>
        </div>
    </PanelLayoutRaw>
</template>
