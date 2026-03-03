<?php

use Avvertix\AgentRequest\LaravelAgentRequest\Actions\DetectAgent;
use Avvertix\AgentRequest\LaravelAgentRequest\Enums\AgentType;

return [

    'enabled' => (bool) env('AGENT_REQUEST_ENABLED', true),

    'detector' => DetectAgent::class,

    /*
    |--------------------------------------------------------------------------
    | Blocked Agent Types
    |--------------------------------------------------------------------------
    |
    | Agent types listed here will be denied by the DenyAgentMiddleware with
    | a 404 response. Remove a type to allow that category through.
    |
    | Can be overridden with a single comma-separated environment variable:
    |
    |   AGENT_REQUEST_BLOCK=assistant,crawler,tool
    |
    */
    'block' => env('AGENT_REQUEST_BLOCK', [
        AgentType::ASSISTANT,
        AgentType::TOOL,
    ]),

];
