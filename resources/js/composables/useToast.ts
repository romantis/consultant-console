type ToastType = 'success' | 'error';

export function useToast() {
    const toast = (type: ToastType, message: string) => {
        if (typeof window === 'undefined') {
            return;
        }

        window.dispatchEvent(
            new CustomEvent('toast:show', {
                detail: { type, message },
            }),
        );
    };

    return { toast };
}
