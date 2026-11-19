type Args = { organization: string | number; orgMetric: string | number };

export const show = (args: Args) => {
    return {
        url: `/organizations/${args.organization}/org-metrics/${args.orgMetric}`,
    };
};

const orgMetrics = {
    show,
};

export default orgMetrics;
