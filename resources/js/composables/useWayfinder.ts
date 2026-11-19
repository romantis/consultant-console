import type { InertiaLinkProps } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import { dashboard, home } from '@/routes';
import metricTemplates from '@/routes/metric-templates';
import org from '@/routes/org';
import owner from '@/routes/owner';
import type { AppPageProps } from '@/types';

type AbilityEvaluator = (props: AppPageProps) => boolean;
type AbilitySpec = string | string[] | AbilityEvaluator | undefined;

type WayfinderRoute<
    T extends (...args: any[]) => NonNullable<InertiaLinkProps['href']>,
> = T & {
    readonly can: boolean;
    readonly ability?: AbilitySpec;
};

function normalizeAbilitySource(source: unknown): Set<string> {
    const abilities = new Set<string>();

    if (Array.isArray(source)) {
        source.forEach((value) => {
            if (typeof value === 'string' && value.length > 0) {
                abilities.add(value);
            }
        });
    } else if (source && typeof source === 'object') {
        Object.entries(source as Record<string, unknown>).forEach(
            ([key, value]) => {
                if (value) {
                    abilities.add(key);
                }
            },
        );
    }

    return abilities;
}

export function useWayfinder() {
    const page = usePage<AppPageProps>();

    const abilitySet = computed(() => {
        const direct = page.props.abilities;
        const nested = page.props.auth?.abilities;

        const combined = new Set<string>();
        normalizeAbilitySource(direct).forEach((value) => combined.add(value));
        normalizeAbilitySource(nested).forEach((value) => combined.add(value));

        return combined;
    });

    const evaluateAbility = (ability?: AbilitySpec) => {
        if (!ability) {
            return true;
        }

        if (typeof ability === 'function') {
            return ability(page.props);
        }

        const list = Array.isArray(ability) ? ability : [ability];
        if (list.length === 0) {
            return true;
        }

        return list.some((item) => {
            if (!item) {
                return false;
            }

            if (item === 'staff') {
                return Boolean(page.props.auth?.user?.is_internal_staff);
            }

            if (item === 'govern') {
                return Boolean(page.props.auth?.user?.can_govern_internal);
            }

            if (item === 'impersonate') {
                return Boolean(page.props.auth?.user?.can_impersonate);
            }

            return abilitySet.value.has(item);
        });
    };

    const decorate = <
        T extends (...args: any[]) => NonNullable<InertiaLinkProps['href']>,
    >(
        resolver: T,
        ability?: AbilitySpec,
    ) => {
        const wrapped = ((...args: Parameters<T>) =>
            resolver(...args)) as WayfinderRoute<T>;

        Object.defineProperty(wrapped, 'can', {
            get: () => evaluateAbility(ability),
        });

        Object.defineProperty(wrapped, 'ability', {
            get: () => ability,
        });

        return wrapped;
    };

    const clientFormsResolver = () => '/client/forms';
    const clientHandbookResolver = () => '/client/handbook';
    const metricLibraryIndexResolver = () => '/metric-library';
    const metricLibraryCreateResolver = () => '/metric-library/create';
    const metricLibraryStoreResolver = () => '/metric-library';
    const metricLibraryEditResolver = (canonicalMetricId: string) =>
        `/metric-library/${canonicalMetricId}/edit`;
    const metricLibraryUpdateResolver = (canonicalMetricId: string) =>
        `/metric-library/${canonicalMetricId}`;
    const metricLibraryDestroyResolver = (canonicalMetricId: string) =>
        `/metric-library/${canonicalMetricId}`;
    const orgMetricsIndexResolver = (organizationId: string) =>
        `/organizations/${organizationId}/org-metrics`;
    const orgMetricsShowResolver = (
        organizationId: string,
        orgMetricId: string,
    ) => `/organizations/${organizationId}/org-metrics/${orgMetricId}`;
    const orgMetricsUpdateResolver = (
        organizationId: string,
        orgMetricId: string,
    ) => `/organizations/${organizationId}/org-metrics/${orgMetricId}`;
    const orgMetricsSignalsResolver = (
        organizationId: string,
        orgMetricId: string,
    ) => `/organizations/${organizationId}/org-metrics/${orgMetricId}/signals`;
    const orgMetricsParticipantsResolver = (
        organizationId: string,
        orgMetricId: string,
    ) =>
        `/organizations/${organizationId}/org-metrics/${orgMetricId}/participants`;
    const orgHandbookExportsIndexResolver = (organizationId: string) =>
        `/organizations/${organizationId}/handbook-exports`;
    const orgHandbookExportsPublishResolver = (
        organizationId: string,
        handbookExportId: string,
    ) =>
        `/organizations/${organizationId}/handbook-exports/${handbookExportId}/publish`;
    const orgHandbookExportsUnpublishResolver = (
        organizationId: string,
        handbookExportId: string,
    ) =>
        `/organizations/${organizationId}/handbook-exports/${handbookExportId}/publish`;
    const orgHandbookExportsDownloadResolver = (
        organizationId: string,
        handbookExportId: string,
    ) =>
        `/organizations/${organizationId}/handbook-exports/${handbookExportId}/download`;

    const orgMetricsRoutes = (
        org as unknown as {
            orgMetrics?: {
                index?: typeof orgMetricsIndexResolver;
                show?: typeof orgMetricsShowResolver;
                update?: typeof orgMetricsUpdateResolver;
                signals?: {
                    update?: typeof orgMetricsSignalsResolver;
                };
                participants?: {
                    update?: typeof orgMetricsParticipantsResolver;
                };
            };
        }
    ).orgMetrics;

    const handbookExportsRoutes = (
        org as unknown as {
            handbookExports?: {
                index?: typeof orgHandbookExportsIndexResolver;
                publish?: typeof orgHandbookExportsPublishResolver;
                unpublish?: typeof orgHandbookExportsUnpublishResolver;
                download?: typeof orgHandbookExportsDownloadResolver;
            };
        }
    ).handbookExports;

    const wayfinder = {
        can: evaluateAbility,
        dashboard: decorate(dashboard),
        home: decorate(home),
        owner: {
            console: decorate(owner.console, 'govern'),
            impersonation: {
                start: decorate(owner.impersonation.start, 'impersonate'),
                stop: decorate(owner.impersonation.stop, 'impersonate'),
            },
        },
        metricTemplates: {
            index: decorate(metricTemplates.index),
            create: decorate(metricTemplates.create),
            store: decorate(metricTemplates.store),
            edit: decorate(metricTemplates.edit),
            preview: decorate(metricTemplates.preview),
            update: decorate(metricTemplates.update),
            destroy: decorate(metricTemplates.destroy),
        },
        metricLibrary: {
            index: decorate(metricLibraryIndexResolver, 'staff'),
            create: decorate(metricLibraryCreateResolver, 'staff'),
            store: decorate(metricLibraryStoreResolver, 'staff'),
            edit: decorate(metricLibraryEditResolver, 'staff'),
            update: decorate(metricLibraryUpdateResolver, 'staff'),
            destroy: decorate(metricLibraryDestroyResolver, 'staff'),
        },
        org: {
            index: decorate(org.index),
            show: decorate(org.show),
            engagements: {
                index: decorate(org.engagements.index),
                show: decorate(org.engagements.show),
            },
            orgMetrics: {
                index: decorate(
                    orgMetricsRoutes?.index ?? orgMetricsIndexResolver,
                    'staff',
                ),
                show: decorate(
                    orgMetricsRoutes?.show ?? orgMetricsShowResolver,
                    'staff',
                ),
                update: decorate(
                    orgMetricsRoutes?.update ?? orgMetricsUpdateResolver,
                    'staff',
                ),
                signals: {
                    update: decorate(
                        orgMetricsRoutes?.signals?.update ??
                            orgMetricsSignalsResolver,
                        'staff',
                    ),
                },
                participants: {
                    update: decorate(
                        orgMetricsRoutes?.participants?.update ??
                            orgMetricsParticipantsResolver,
                        'staff',
                    ),
                },
            },
            handbookExports: {
                index: decorate(
                    handbookExportsRoutes?.index ??
                        orgHandbookExportsIndexResolver,
                ),
                publish: decorate(
                    handbookExportsRoutes?.publish ??
                        orgHandbookExportsPublishResolver,
                ),
                unpublish: decorate(
                    handbookExportsRoutes?.unpublish ??
                        orgHandbookExportsUnpublishResolver,
                ),
                download: decorate(
                    handbookExportsRoutes?.download ??
                        orgHandbookExportsDownloadResolver,
                ),
            },
            settings: {
                edit: decorate(org.settings.edit),
            },
        },
        client: {
            forms: {
                index: decorate(clientFormsResolver),
            },
            handbook: {
                index: decorate(clientHandbookResolver),
            },
            organizations: {
                show: decorate(org.show),
            },
            engagements: {
                show: decorate(org.engagements.show),
                forms: {
                    index: decorate(org.engagements.forms.index),
                },
            },
        },
    };

    return wayfinder;
}
