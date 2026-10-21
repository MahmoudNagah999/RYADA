<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Resolve the locale: Accept-Language header > user locale > app default.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->fromHeader($request)
            ?? $this->fromUser($request)
            ?? config('app.locale');

        app()->setLocale($locale);

        $response = $next($request);
        $response->headers->set('Content-Language', $locale);

        return $response;
    }

    private function fromHeader(Request $request): ?string
    {
        foreach ($request->getLanguages() as $language) {
            $code = strtolower(explode('_', $language)[0]);

            if ($this->isSupported($code)) {
                return $code;
            }
        }

        return null;
    }

    private function fromUser(Request $request): ?string
    {
        // SetLocale runs before auth:sanctum, so resolve the user through the sanctum guard explicitly.
        $locale = $request->user('sanctum')?->locale;

        return $this->isSupported($locale) ? $locale : null;
    }

    private function isSupported(?string $locale): bool
    {
        return $locale !== null && in_array($locale, config('locales.supported'), true);
    }
}
