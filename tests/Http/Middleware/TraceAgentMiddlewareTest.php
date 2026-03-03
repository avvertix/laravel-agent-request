<?php

use Avvertix\AgentRequest\LaravelAgentRequest\Enums\AgentType;
use Avvertix\AgentRequest\LaravelAgentRequest\Http\Middleware\TraceAgentMiddleware;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Context;

function makeTraceRequest(string $userAgent, array $headers = []): Request
{
    $request = Request::create('https://example.com/', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => $userAgent,
    ]);

    foreach ($headers as $name => $value) {
        $request->headers->set($name, $value);
    }

    return $request;
}

beforeEach(fn () => Context::flush());

// ---------------------------------------------------------------------------
// Pass-through
// ---------------------------------------------------------------------------

it('always passes the request to the next handler', function () {
    $response = (new TraceAgentMiddleware)->handle(makeTraceRequest('GPTBot/1.0'), fn () => new Response('ok'));

    expect($response->getContent())->toBe('ok');
});

it('passes non-agent requests through without touching context', function () {
    (new TraceAgentMiddleware)->handle(makeTraceRequest('Mozilla/5.0 (Windows NT 10.0)'), fn () => new Response('ok'));

    expect(Context::get('agent_name'))->toBeNull();
    expect(Context::get('agent_type'))->toBeNull();
});

// ---------------------------------------------------------------------------
// Context enrichment
// ---------------------------------------------------------------------------

it('adds agent_name and agent_type to context for an AI assistant', function () {
    (new TraceAgentMiddleware)->handle(makeTraceRequest('Claude-User/1.0'), fn () => new Response('ok'));

    expect(Context::get('agent_name'))->toBe('Claude');
    expect(Context::get('agent_type'))->toBe(AgentType::ASSISTANT);
});

it('adds agent_name and agent_type to context for an AI crawler', function () {
    (new TraceAgentMiddleware)->handle(makeTraceRequest('GPTBot/1.0'), fn () => new Response('ok'));

    expect(Context::get('agent_name'))->toBe('GPTBot');
    expect(Context::get('agent_type'))->toBe(AgentType::CRAWLER);
});

it('adds agent_name and agent_type to context for an AI tool', function () {
    (new TraceAgentMiddleware)->handle(makeTraceRequest('FirecrawlAgent/1.0'), fn () => new Response('ok'));

    expect(Context::get('agent_name'))->toBe('Firecrawl');
    expect(Context::get('agent_type'))->toBe(AgentType::TOOL);
});

it('adds CloudflareBrowserRendering context when a Cloudflare rendering header is present', function () {
    (new TraceAgentMiddleware)->handle(makeTraceRequest('', ['cf-brapi-request-id' => 'abc123']), fn () => new Response('ok'));

    expect(Context::get('agent_name'))->toBe('CloudflareBrowserRendering');
    expect(Context::get('agent_type'))->toBe(AgentType::TOOL);
});

it('does not add context when the user agent is empty', function () {
    (new TraceAgentMiddleware)->handle(makeTraceRequest(''), fn () => new Response('ok'));

    expect(Context::get('agent_name'))->toBeNull();
    expect(Context::get('agent_type'))->toBeNull();
});

// ---------------------------------------------------------------------------
// enabled flag
// ---------------------------------------------------------------------------

it('skips context enrichment when disabled via config', function () {
    config()->set('agent-request.enabled', false);

    (new TraceAgentMiddleware)->handle(makeTraceRequest('GPTBot/1.0'), fn () => new Response('ok'));

    expect(Context::get('agent_name'))->toBeNull();
    expect(Context::get('agent_type'))->toBeNull();
});

it('still passes the request through when disabled', function () {
    config()->set('agent-request.enabled', false);

    $response = (new TraceAgentMiddleware)->handle(makeTraceRequest('GPTBot/1.0'), fn () => new Response('ok'));

    expect($response->getContent())->toBe('ok');
});

it('enriches context when enabled is explicitly true', function () {
    config()->set('agent-request.enabled', true);

    (new TraceAgentMiddleware)->handle(makeTraceRequest('GPTBot/1.0'), fn () => new Response('ok'));

    expect(Context::get('agent_name'))->toBe('GPTBot');
    expect(Context::get('agent_type'))->toBe(AgentType::CRAWLER);
});
