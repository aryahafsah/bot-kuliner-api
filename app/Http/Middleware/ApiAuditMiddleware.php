<?php

namespace App\Http\Middleware;

use App\Models\ApiAuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiAuditMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);
        $response = $next($request);

        ApiAuditLog::create([
            'user_id' => $request->user()?->id,
            'method' => $request->method(),
            'path' => $request->path(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_headers' => $this->sanitizeHeaders($request->headers->all()),
            'request_body' => $this->sanitizeBody($request->all()),
            'response_status' => $response->getStatusCode(),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        return $response;
    }

    private function sanitizeHeaders(array $headers): array
    {
        foreach (['authorization', 'cookie', 'x-api-key'] as $key) {
            unset($headers[$key]);
        }

        return $headers;
    }

    private function sanitizeBody(array $body): array
    {
        foreach (['password', 'password_confirmation', 'token', 'code', 'otp', 'secret'] as $key) {
            if (array_key_exists($key, $body)) {
                $body[$key] = '[REDACTED]';
            }
        }

        return $body;
    }
}
