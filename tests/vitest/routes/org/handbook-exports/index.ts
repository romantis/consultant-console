export const index = (organizationId: string | number) => ({
    url: `/organizations/${organizationId}/handbook-exports`,
    method: 'get',
});

const handbookExports = {
    index,
};

export default handbookExports;
