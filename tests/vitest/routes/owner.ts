export const console = () => '/owner/console';

export const impersonation = {
    start: () => '/owner/impersonation/start',
    stop: () => '/owner/impersonation/stop',
};

export default {
    console,
    impersonation,
};
