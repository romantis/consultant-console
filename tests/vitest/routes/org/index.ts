type SimpleRoute = { url: string; method: string };

import * as engagementRoutes from './engagements';
import * as orgSettingsRoutes from './settings';

const makeRoute = (url: string, method: string = 'get'): SimpleRoute => ({
    url,
    method,
});

const org = {
    index: () => makeRoute('/organizations'),
    show: (id: string | number) => makeRoute(`/organizations/${id}`),
    create: () => makeRoute('/organizations/create'),
    engagements: {
        index: engagementRoutes.index,
        show: engagementRoutes.show,
        forms: {
            index: (params: [string | number, string | number]) => {
                const [orgId, engagementId] = params;
                return `/organizations/${orgId}/engagements/${engagementId}/forms`;
            },
        },
    },
    settings: {
        edit: orgSettingsRoutes.edit,
    },
};

export default org;
export const index = org.index;
export const show = org.show;
export const create = org.create;
