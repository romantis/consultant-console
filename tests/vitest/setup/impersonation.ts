import type { ImpersonationState, Organization } from '@/types';

export type ImpersonationOverrides = Partial<ImpersonationState> & {
    organization?: Partial<Organization>;
};

const defaultOrganization: Organization = {
    id: 'org-impersonation',
    name: 'Acme Corp',
    slug: 'acme-corp',
};

export function buildImpersonationState(
    overrides: ImpersonationOverrides = {},
): ImpersonationState {
    const organization: Organization = {
        ...defaultOrganization,
        ...(overrides.organization ?? {}),
    };

    return {
        organization,
        role: overrides.role ?? 'viewer',
        started_at: overrides.started_at ?? new Date().toISOString(),
        justification: overrides.justification ?? null,
    };
}
