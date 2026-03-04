<?php

declare(strict_types=1);

namespace Avvertix\AgentRequest\LaravelAgentRequest\Http\Middleware;

use Avvertix\AgentRequest\LaravelAgentRequest\Concern\InteractWithDetector;
use Avvertix\AgentRequest\LaravelAgentRequest\Enums\AgentType;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DenyAgentMiddleware
{
    use InteractWithDetector;

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('agent-request.enabled', true)) {
            return $next($request);
        }

        $blocked = $this->resolveBlockedTypes(config('agent-request.block', []));

        if (in_array($this->detector($request)->detect(), $blocked, strict: true)) {
            // TODO: maybe identify a way to report
            abort(Response::HTTP_NOT_FOUND);
        }

        return $next($request);
    }

    /**
     * Normalize the configured block list to a list of AgentType cases.
     *
     * Accepts either an array of AgentType instances (set directly in the
     * published config) or a comma-separated string (from the
     * AGENT_REQUEST_BLOCK environment variable). Unknown values are silently
     * ignored.
     *
     * @return list<AgentType>
     */
    private function resolveBlockedTypes(array|string $value): array
    {
        if (is_string($value)) {
            return array_values(array_filter(array_map(
                fn (string $v) => AgentType::tryFrom(trim($v)),
                explode(',', $value)
            )));
        }

        return array_values($value);
    }
}
