import { useWayfinder } from '@/composables/useWayfinder';
import type { AppPageProps, ClientNavigation } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

type ClientNavPageProps = AppPageProps<{
    clientNavigation?: ClientNavigation | null;
}>;
type WorkspaceLinks = Record<'metrics' | 'handbook' | 'support', string | null>;

const resolveRouteUrl = (route: unknown): string | null => {
    if (typeof route === 'string') {
        return route;
    }

    if (route && typeof route === 'object') {
        if (
            'url' in route &&
            typeof (route as { url?: string }).url === 'string'
        ) {
            return (route as { url: string }).url;
        }

        if (
            'href' in route &&
            typeof (route as { href?: string }).href === 'string'
        ) {
            return (route as { href: string }).href;
        }
    }

    return null;
};

export function useClientNav() {
    const page = usePage<ClientNavPageProps>();
    const wayfinder = useWayfinder();

    const navigation = computed<ClientNavigation | null>(() => {
        const payload = page.props.clientNavigation;
        if (!payload || typeof payload !== 'object') {
            return null;
        }

        return payload as ClientNavigation;
    });

    const shortcuts = computed(() => navigation.value?.links ?? null);
    const organization = computed(() => navigation.value?.organization ?? null);
    const membership = computed(() => navigation.value?.membership ?? null);
    const activeEngagementId = computed(
        () => navigation.value?.activeEngagementId ?? null,
    );
    const engagements = computed(() => {
        const orgId = organization.value?.id ?? null;
        const items = navigation.value?.engagements ?? [];

        if (!orgId) {
            return items;
        }

        return items.map((engagement) => {
            if (engagement.href) {
                return engagement;
            }

            return {
                ...engagement,
                href: wayfinder.org.engagements.show([orgId, engagement.id]),
            };
        });
    });

    const workspaceLinks = computed<WorkspaceLinks>(() => {
        const links = shortcuts.value ?? null;

        return {
            metrics: null,
            handbook: resolveRouteUrl(links?.handbook ?? null),
            support: resolveRouteUrl(links?.support ?? null),
        };
    });

    const handbookMetrics = computed(() => {
        const items = navigation.value?.handbookMetrics ?? [];

        return items.map((item) => ({
            ...item,
            href: resolveRouteUrl(item?.href ?? null),
        }));
    });

    return {
        navigation,
        organization,
        membership,
        shortcuts,
        engagements,
        activeEngagementId,
        workspaceLinks,
        handbookMetrics,
    };
}
