<?php

use Avvertix\AgentRequest\LaravelAgentRequest\Enums\AgentType;
use Avvertix\AgentRequest\LaravelAgentRequest\Http\AgentRequest;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function makeTypedRequest(string $userAgent, array $headers = [], string $accept = '*/*'): AgentRequest
{
    $request = AgentRequest::create('https://example.com/path', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => $userAgent,
        'HTTP_ACCEPT' => $accept,
    ]);

    foreach ($headers as $name => $value) {
        $request->headers->set($name, $value);
    }

    return $request;
}

// ---------------------------------------------------------------------------
// isAgent / detect
// ---------------------------------------------------------------------------

it('isAgent returns true for a known AI user agent', function () {
    expect(makeTypedRequest('GPTBot/1.0')->isAgent())->toBeTrue();
});

it('isAgent returns false for a regular browser', function () {
    expect(makeTypedRequest('Mozilla/5.0')->isAgent())->toBeFalse();
});

it('detect returns the agent type for a known bot', function () {
    $request = makeTypedRequest('GPTBot/1.0');

    expect($request->detect())->toBe(AgentType::CRAWLER);
    expect($request->agentName())->toBe('GPTBot');
});

it('detect returns HUMAN for an unrecognised user agent', function () {
    $request = makeTypedRequest('Mozilla/5.0');

    expect($request->detect())->toBe(AgentType::HUMAN);
    expect($request->agentName())->toBeNull();
});

it('isAiAssistant returns true for a known assistant', function () {
    expect(makeTypedRequest('Claude-User/1.0')->isAiAssistant())->toBeTrue();
});

it('isAiCrawler returns true for a known crawler', function () {
    expect(makeTypedRequest('ClaudeBot/1.0')->isAiCrawler())->toBeTrue();
});

it('isAiTool returns true for a known AI tool', function () {
    expect(makeTypedRequest('FirecrawlAgent/1.0')->isAiTool())->toBeTrue();
});

it('isCloudflareBrowserRendering returns true when the cf-brapi-request-id header is present', function () {
    $request = makeTypedRequest('', ['cf-brapi-request-id' => 'abc123']);

    expect($request->isCloudflareBrowserRendering())->toBeTrue();
});

// ---------------------------------------------------------------------------
// wantsMarkdown / expectsMarkdown
// ---------------------------------------------------------------------------

it('wantsMarkdown returns true when Accept is text/markdown', function () {
    expect(makeTypedRequest('Mozilla/5.0', [], 'text/markdown')->wantsMarkdown())->toBeTrue();
});

it('wantsMarkdown returns true when Accept is text/x-markdown', function () {
    expect(makeTypedRequest('Mozilla/5.0', [], 'text/x-markdown')->wantsMarkdown())->toBeTrue();
});

it('wantsMarkdown returns false when Accept is application/json', function () {
    expect(makeTypedRequest('Mozilla/5.0', [], 'application/json')->wantsMarkdown())->toBeFalse();
});

it('expectsMarkdown returns true when Accept is text/plain', function () {
    expect(makeTypedRequest('Mozilla/5.0', [], 'text/plain')->expectsMarkdown())->toBeTrue();
});

it('expectsMarkdown returns true when Accept is text/markdown', function () {
    expect(makeTypedRequest('Mozilla/5.0', [], 'text/markdown')->expectsMarkdown())->toBeTrue();
});

it('expectsMarkdown returns false for application/json', function () {
    expect(makeTypedRequest('Mozilla/5.0', [], 'application/json')->expectsMarkdown())->toBeFalse();
});
