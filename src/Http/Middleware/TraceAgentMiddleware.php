<?php

declare(strict_types=1);

namespace Avvertix\AgentRequest\LaravelAgentRequest\Http\Middleware;

use Avvertix\AgentRequest\LaravelAgentRequest\Concern\InteractWithDetector;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

class TraceAgentMiddleware
{
    use InteractWithDetector;

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('agent-request.enabled', true)) {
            return $next($request);
        }

        $detectAgent = $this->detector($request);

        if ($detectAgent->isAgent()) {
            Context::add([
                'agent_name' => $detectAgent->agentName(),
                'agent_type' => $detectAgent->detect(),
            ]);
        }

        return $next($request);
    }
}
