<?php

use Avvertix\AgentRequest\LaravelAgentRequest\Enums\AgentType;
use Avvertix\AgentRequest\LaravelAgentRequest\Http\Middleware\DenyAgentMiddleware;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

function makeMiddlewareRequest(string $userAgent): Request
{
    return Request::create('https://example.com/', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => $userAgent,
    ]);
}

// ---------------------------------------------------------------------------
// Default config behaviour (ASSISTANT + TOOL blocked, CRAWLER allowed)
// ---------------------------------------------------------------------------

it('allows regular browser requests through', function () {
    $middleware = new DenyAgentMiddleware;
    $request = makeMiddlewareRequest('Mozilla/5.0 (Windows NT 10.0; Win64; x64)');

    $response = $middleware->handle($request, fn () => new Response('ok'));

    expect($response->getContent())->toBe('ok');
});

it('blocks requests from known AI assistants with a 404 by default', function () {
    $middleware = new DenyAgentMiddleware;

    expect(fn () => $middleware->handle(makeMiddlewareRequest('Claude-User/1.0'), fn () => new Response('ok')))
        ->toThrow(NotFoundHttpException::class);
});

it('blocks requests from known AI tools with a 404 by default', function () {
    $middleware = new DenyAgentMiddleware;

    expect(fn () => $middleware->handle(makeMiddlewareRequest('FirecrawlAgent/1.0'), fn () => new Response('ok')))
        ->toThrow(NotFoundHttpException::class);
});

it('allows requests from AI crawlers through by default', function () {
    $middleware = new DenyAgentMiddleware;

    $response = $middleware->handle(makeMiddlewareRequest('GPTBot/1.0'), fn () => new Response('ok'));

    expect($response->getContent())->toBe('ok');
});

it('allows requests with an empty user agent', function () {
    $middleware = new DenyAgentMiddleware;
    $request = makeMiddlewareRequest('');

    $response = $middleware->handle($request, fn () => new Response('ok'));

    expect($response->getContent())->toBe('ok');
});

// ---------------------------------------------------------------------------
// enabled flag
// ---------------------------------------------------------------------------

it('passes all requests through when disabled via config', function () {
    config()->set('agent-request.enabled', false);

    expect((new DenyAgentMiddleware)->handle(makeMiddlewareRequest('Claude-User/1.0'), fn () => new Response('ok'))->getContent())->toBe('ok');
    expect((new DenyAgentMiddleware)->handle(makeMiddlewareRequest('FirecrawlAgent/1.0'), fn () => new Response('ok'))->getContent())->toBe('ok');
    expect((new DenyAgentMiddleware)->handle(makeMiddlewareRequest('GPTBot/1.0'), fn () => new Response('ok'))->getContent())->toBe('ok');
});

it('still blocks agents when enabled is explicitly true', function () {
    config()->set('agent-request.enabled', true);

    $middleware = new DenyAgentMiddleware;

    expect(fn () => $middleware->handle(makeMiddlewareRequest('Claude-User/1.0'), fn () => new Response('ok')))
        ->toThrow(NotFoundHttpException::class);
});

// ---------------------------------------------------------------------------
// Array-based selective blocking
// ---------------------------------------------------------------------------

it('blocks AI crawlers when CRAWLER is explicitly added to the block list', function () {
    config()->set('agent-request.block', [AgentType::CRAWLER]);

    $middleware = new DenyAgentMiddleware;

    expect(fn () => $middleware->handle(makeMiddlewareRequest('GPTBot/1.0'), fn () => new Response('ok')))
        ->toThrow(NotFoundHttpException::class);
});

it('allows an AI assistant through when ASSISTANT is not in the block list', function () {
    config()->set('agent-request.block', [AgentType::CRAWLER, AgentType::TOOL]);

    $middleware = new DenyAgentMiddleware;
    $response = $middleware->handle(makeMiddlewareRequest('Claude-User/1.0'), fn () => new Response('ok'));

    expect($response->getContent())->toBe('ok');
});

it('allows an AI crawler through when CRAWLER is not in the block list', function () {
    config()->set('agent-request.block', [AgentType::ASSISTANT, AgentType::TOOL]);

    $middleware = new DenyAgentMiddleware;
    $response = $middleware->handle(makeMiddlewareRequest('GPTBot/1.0'), fn () => new Response('ok'));

    expect($response->getContent())->toBe('ok');
});

it('allows an AI tool through when TOOL is not in the block list', function () {
    config()->set('agent-request.block', [AgentType::ASSISTANT, AgentType::CRAWLER]);

    $middleware = new DenyAgentMiddleware;
    $response = $middleware->handle(makeMiddlewareRequest('FirecrawlAgent/1.0'), fn () => new Response('ok'));

    expect($response->getContent())->toBe('ok');
});

it('allows all agents through when the block list is empty', function () {
    config()->set('agent-request.block', []);

    expect((new DenyAgentMiddleware)->handle(makeMiddlewareRequest('GPTBot/1.0'), fn () => new Response('ok'))->getContent())->toBe('ok');
    expect((new DenyAgentMiddleware)->handle(makeMiddlewareRequest('Claude-User/1.0'), fn () => new Response('ok'))->getContent())->toBe('ok');
    expect((new DenyAgentMiddleware)->handle(makeMiddlewareRequest('FirecrawlAgent/1.0'), fn () => new Response('ok'))->getContent())->toBe('ok');
});

// ---------------------------------------------------------------------------
// Env-var string format (AGENT_REQUEST_BLOCK=assistant,crawler,tool)
// ---------------------------------------------------------------------------

it('blocks all agent types when block is set to a full comma-separated string', function () {
    config()->set('agent-request.block', 'assistant,crawler,tool');

    expect(fn () => (new DenyAgentMiddleware)->handle(makeMiddlewareRequest('GPTBot/1.0'), fn () => new Response('ok')))
        ->toThrow(NotFoundHttpException::class);
    expect(fn () => (new DenyAgentMiddleware)->handle(makeMiddlewareRequest('Claude-User/1.0'), fn () => new Response('ok')))
        ->toThrow(NotFoundHttpException::class);
    expect(fn () => (new DenyAgentMiddleware)->handle(makeMiddlewareRequest('FirecrawlAgent/1.0'), fn () => new Response('ok')))
        ->toThrow(NotFoundHttpException::class);
});

it('blocks only the listed types when block is a partial comma-separated string', function () {
    config()->set('agent-request.block', 'crawler');

    expect(fn () => (new DenyAgentMiddleware)->handle(makeMiddlewareRequest('GPTBot/1.0'), fn () => new Response('ok')))
        ->toThrow(NotFoundHttpException::class);
    expect((new DenyAgentMiddleware)->handle(makeMiddlewareRequest('Claude-User/1.0'), fn () => new Response('ok'))->getContent())
        ->toBe('ok');
    expect((new DenyAgentMiddleware)->handle(makeMiddlewareRequest('FirecrawlAgent/1.0'), fn () => new Response('ok'))->getContent())
        ->toBe('ok');
});

it('ignores unknown values in a comma-separated string', function () {
    config()->set('agent-request.block', 'crawler,unknown_type');

    expect(fn () => (new DenyAgentMiddleware)->handle(makeMiddlewareRequest('GPTBot/1.0'), fn () => new Response('ok')))
        ->toThrow(NotFoundHttpException::class);
    expect((new DenyAgentMiddleware)->handle(makeMiddlewareRequest('Claude-User/1.0'), fn () => new Response('ok'))->getContent())
        ->toBe('ok');
});

it('allows all agents through when block is an empty string', function () {
    config()->set('agent-request.block', '');

    $middleware = new DenyAgentMiddleware;

    expect($middleware->handle(makeMiddlewareRequest('GPTBot/1.0'), fn () => new Response('ok'))->getContent())
        ->toBe('ok');
});
