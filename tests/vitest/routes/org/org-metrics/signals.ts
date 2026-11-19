type Args = { organization: string | number; orgMetric: string | number };

export const update = (args: Args) => {
    return {
        url: `/organizations/${args.organization}/org-metrics/${args.orgMetric}/signals`,
        method: 'patch',
    };
};

const signals = {
    update,
};

export default signals;
