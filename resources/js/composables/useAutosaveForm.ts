import { resolveCsrfHeaders } from '@/lib/csrf';
import type { Ref } from 'vue';
import { onBeforeUnmount, ref } from 'vue';

export type AutosaveState = 'idle' | 'saving' | 'saved' | 'error';

export const AUTOSAVE_THROTTLE_MESSAGE =
    'Autosave temporarily throttled. Retrying shortly.';

type EndpointLike =
    | string
    | URL
    | null
    | undefined
    | {
          url?: string | null;
          href?: string | null;
      }
    | (() =>
          | string
          | URL
          | null
          | undefined
          | { url?: string | null; href?: string | null });

export interface UseAutosaveFormOptions<TPayload> {
    endpoint: EndpointLike;
    method?: string;
    debounceMs?: number;
    resetDelayMs?: number;
    minThrottleDelayMs?: number;
    throttleMessage?: string;
    headers?: Record<string, string>;
    includeCsrfToken?: boolean;
    credentials?: RequestCredentials;
    isDisabled?: () => boolean;
    buildPayload: () => TPayload;
    localStorageKey?: string;
    localStorageEvent?: string;
    localStoragePayload?: () => Record<string, unknown>;
    onSuccess?: (data: unknown, response: Response) => void;
    onError?: (error: unknown) => void;
}

export interface UseAutosaveFormReturn {
    autosaveState: Ref<AutosaveState>;
    autosaveError: Ref<string>;
    scheduleAutosave: (delayMs?: number) => void;
    cancelAutosave: () => void;
    performAutosave: () => Promise<void>;
}

const resolveEndpoint = (endpoint: EndpointLike): string => {
    const value = typeof endpoint === 'function' ? endpoint() : endpoint;

    if (!value) {
        return '';
    }

    if (value instanceof URL) {
        return value.toString();
    }

    if (typeof value === 'string') {
        return value;
    }

    if (typeof value === 'object') {
        if (typeof value.url === 'string') {
            return value.url;
        }
        if (typeof value.href === 'string') {
            return value.href;
        }
    }

    return '';
};

const shouldParseJson = (response: Response): boolean => {
    const { headers } = response;
    if (!headers || typeof headers.get !== 'function') {
        return false;
    }

    const header = headers.get('Content-Type') ?? headers.get('content-type');
    return Boolean(header && header.toLowerCase().includes('application/json'));
};

const resolveRetryAfterSeconds = async (
    response: Response,
): Promise<number> => {
    const headers = response.headers;
    if (headers && typeof headers.get === 'function') {
        const headerRetry = Number.parseInt(
            headers.get('Retry-After') ?? '',
            10,
        );
        if (Number.isFinite(headerRetry) && headerRetry > 0) {
            return headerRetry;
        }
    }

    const parseBody = async () => {
        if (typeof response.clone === 'function') {
            try {
                return await response.clone().json();
            } catch {
                return null;
            }
        }

        if (typeof (response as { json?: unknown }).json === 'function') {
            try {
                return await (
                    response as { json: () => Promise<unknown> }
                ).json();
            } catch {
                return null;
            }
        }

        return null;
    };

    const payload = await parseBody();
    if (payload && typeof payload === 'object') {
        const bodyRetry = Number(
            (payload as Record<string, unknown>).retry_after ??
                (payload as Record<string, unknown>).retryAfter ??
                0,
        );
        if (Number.isFinite(bodyRetry) && bodyRetry > 0) {
            return bodyRetry;
        }
    }

    return 1;
};

const defaultErrorMessage = 'Unable to save progress right now.';

export function useAutosaveForm<TPayload = Record<string, unknown>>(
    options: UseAutosaveFormOptions<TPayload>,
): UseAutosaveFormReturn {
    const autosaveState = ref<AutosaveState>('idle');
    const autosaveError = ref('');

    let autosaveTimer: number | null = null;
    let resetTimer: number | null = null;

    const clearAutosaveTimer = () => {
        if (typeof window === 'undefined') {
            return;
        }

        if (autosaveTimer !== null) {
            window.clearTimeout(autosaveTimer);
            autosaveTimer = null;
        }
    };

    const clearResetTimer = () => {
        if (typeof window === 'undefined') {
            return;
        }

        if (resetTimer !== null) {
            window.clearTimeout(resetTimer);
            resetTimer = null;
        }
    };

    const scheduleAutosave = (delayMs?: number) => {
        if (typeof window === 'undefined') {
            return;
        }

        const delay =
            typeof delayMs === 'number' && Number.isFinite(delayMs)
                ? delayMs
                : (options.debounceMs ?? 600);

        clearAutosaveTimer();

        if (delay <= 0) {
            void performAutosave();
            return;
        }

        autosaveTimer = window.setTimeout(() => {
            autosaveTimer = null;
            void performAutosave();
        }, delay);
    };

    const recordLocalStorage = (event: string) => {
        if (typeof window === 'undefined') {
            return;
        }

        const key = options.localStorageKey ?? 'mh:lastSubmissionUpdate';

        try {
            const payload = {
                event,
                ts: Date.now(),
                ...(options.localStoragePayload?.() ?? {}),
            };

            window.localStorage.setItem(key, JSON.stringify(payload));
        } catch (error) {
            console.debug('Unable to persist submission update', error);
        }
    };

    const performAutosave = async () => {
        if (options.isDisabled?.()) {
            return;
        }

        const url = resolveEndpoint(options.endpoint);
        if (!url) {
            console.error('Autosave endpoint is not defined.');
            return;
        }

        autosaveState.value = 'saving';
        autosaveError.value = '';
        clearResetTimer();

        const headers: Record<string, string> = {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(options.headers ?? {}),
        };

        if (options.includeCsrfToken ?? true) {
            const csrfHeaders = resolveCsrfHeaders();
            Object.entries(csrfHeaders).forEach(([key, value]) => {
                headers[key] = headers[key] ?? value;
            });
        }

        try {
            const response = await fetch(url, {
                method: options.method ?? 'PATCH',
                headers,
                credentials: options.credentials ?? 'same-origin',
                body: JSON.stringify(options.buildPayload()),
            });

            if (response.status === 409) {
                const retryAfterSeconds =
                    await resolveRetryAfterSeconds(response);
                const retryDelayMs = Math.max(
                    options.minThrottleDelayMs ?? 1000,
                    Math.round(retryAfterSeconds * 1000),
                );

                autosaveState.value = 'error';
                autosaveError.value =
                    options.throttleMessage ?? AUTOSAVE_THROTTLE_MESSAGE;

                scheduleAutosave(retryDelayMs);
                return;
            }

            if (!response.ok) {
                throw new Error(`Autosave failed (${response.status})`);
            }

            let data: unknown = null;
            if (shouldParseJson(response)) {
                if (typeof response.clone === 'function') {
                    data = await response
                        .clone()
                        .json()
                        .catch(() => null);
                } else if (
                    typeof (response as { json?: unknown }).json === 'function'
                ) {
                    data = await (response as { json: () => Promise<unknown> })
                        .json()
                        .catch(() => null);
                }
            }

            autosaveState.value = 'saved';

            if (typeof window !== 'undefined') {
                clearResetTimer();
                const resetDelay = options.resetDelayMs ?? 2500;

                if (resetDelay > 0) {
                    resetTimer = window.setTimeout(() => {
                        autosaveState.value = 'idle';
                        resetTimer = null;
                    }, resetDelay);
                }
            }

            if (options.localStorageEvent) {
                recordLocalStorage(options.localStorageEvent);
            }

            options.onSuccess?.(data, response);
        } catch (error) {
            console.error(error);
            autosaveState.value = 'error';
            autosaveError.value =
                error instanceof Error && error.message
                    ? error.message
                    : defaultErrorMessage;
            options.onError?.(error);
        }
    };

    const cancelAutosave = () => {
        clearAutosaveTimer();
        clearResetTimer();
    };

    onBeforeUnmount(cancelAutosave);

    return {
        autosaveState,
        autosaveError,
        scheduleAutosave,
        cancelAutosave,
        performAutosave,
    };
}
