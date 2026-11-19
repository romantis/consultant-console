<?php

namespace App\Http\Middleware;

use App\Models\FormAssignment;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ThrottleAutosave
{
    private const MESSAGE = 'Autosave temporarily throttled.';

    private const MAX_ATTEMPTS = 1;

    public function handle(Request $request, Closure $next): Response
    {
        $assignmentParam = $request->route('assignment');
        $assignment = $assignmentParam instanceof FormAssignment
            ? $assignmentParam
            : (is_string($assignmentParam) || is_numeric($assignmentParam)
                ? FormAssignment::query()->find($assignmentParam)
                : null);
        $user = $request->user();

        if (! $assignment instanceof FormAssignment || ! $user) {
            /** @var Response */
            return $next($request);
        }

        $formId = $assignment->form_id;

        if (! $formId) {
            /** @var Response */
            return $next($request);
        }

        $key = sprintf('form-autosave:%s:%s', $formId, $user->getAuthIdentifier());

        if (! RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            /** @var Response */
            return $next($request);
        }

        $retryAfter = (int) RateLimiter::availableIn($key);
        $retryAfter = $retryAfter > 0 ? $retryAfter : 1;

        $payload = [
            'message' => self::MESSAGE,
            'retry_after' => $retryAfter,
        ];

        $response = $request->expectsJson()
            ? response()->json($payload, 409)
            : response(self::MESSAGE, 409);

        if ($response instanceof JsonResponse) {
            $response->headers->set('Retry-After', (string) $retryAfter);

            return $response;
        }

        $response->headers->set('Content-Type', 'text/plain; charset=UTF-8');
        $response->headers->set('Retry-After', (string) $retryAfter);

        return $response;
    }
}
