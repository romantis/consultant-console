import * as finalDefinition from './final-definition';

type RouteParams = [string | number, string | number];
type SignOffParams = [string | number, string | number, string | number];

const metricRoute = (params: RouteParams, suffix = '') => {
    const [organizationId, metricId] = params;
    return `/organizations/${organizationId}/metrics/${metricId}${suffix}`;
};

export const show = (params: RouteParams) => metricRoute(params);

export const signOff = (params: SignOffParams) => {
    const [organizationId, engagementId, metricId] = params;
    return `/organizations/${organizationId}/engagements/${engagementId}/metrics/${metricId}/sign-off`;
};

const metrics = {
    show,
    finalDefinition,
    signOff,
};

export default metrics;
