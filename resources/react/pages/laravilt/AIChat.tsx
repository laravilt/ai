import { Head, usePage } from '@inertiajs/react';
import PanelLayout from '@laravilt/panel/layouts/PanelLayout';
import { useLocalization } from '@laravilt/support/composables';
import { useMemo } from 'react';

import AIChat from '../../components/AIChat';

interface BreadcrumbItem {
    label: string;
    url: string | null;
}

interface AIConfig {
    configured: boolean;
    default: string;
    providers: Record<string, any>;
}

export interface AIChatPageProps {
    breadcrumbs?: BreadcrumbItem[];
    aiConfig?: AIConfig;
    hasAI?: boolean;
}

export default function AIChatPage({ breadcrumbs }: AIChatPageProps) {
    const { trans } = useLocalization();
    const page = usePage();

    // Get the endpoint based on the panel path
    const panelPath = ((page.props as any)?.panel as { path?: string } | undefined)?.path || 'admin';
    const endpoint = `/${panelPath}/ai`;

    // Transform breadcrumbs to frontend format
    const transformedBreadcrumbs = useMemo(() => {
        if (!breadcrumbs) return [];

        return breadcrumbs.map((item) => ({
            title: item.label,
            href: item.url || '#',
        }));
    }, [breadcrumbs]);

    const pageTitle = trans('laravilt-ai::ai.chat.title');

    return (
        <>
            <Head title={pageTitle} />

            <PanelLayout breadcrumbs={transformedBreadcrumbs}>
                <div className="flex flex-1 flex-col p-4">
                    <div className="h-[calc(100vh-12rem)]">
                        <AIChat showSidebar={true} endpoint={endpoint} />
                    </div>
                </div>
            </PanelLayout>
        </>
    );
}
