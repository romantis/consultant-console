const editRoute = {
    url: '/settings/profile',
    method: 'get',
    toString: () => '/settings/profile',
};

export const edit = () => editRoute;
edit.url = () => editRoute.url;

export default edit;
