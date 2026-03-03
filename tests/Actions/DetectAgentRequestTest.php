<?php

use Avvertix\AgentRequest\LaravelAgentRequest\Actions\DetectAgent;
use Avvertix\AgentRequest\LaravelAgentRequest\Enums\AgentType;
use Illuminate\Http\Request;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Build a Request with the given User-Agent and optional extra headers.
 * Passing an empty string for $userAgent leaves the UA blank (not absent).
 */
function makeAgentRequest(string $userAgent, array $headers = []): Request
{
    $request = Request::create('https://example.com/path?q=1', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => $userAgent,
    ]);

    foreach ($headers as $name => $value) {
        $request->headers->set($name, $value);
    }

    return $request;
}

/**
 * Build a Request with the User-Agent header completely removed.
 */
function makeRequestWithoutUA(array $headers = []): Request
{
    $request = Request::create('https://example.com/path?q=1', 'GET');
    $request->headers->remove('user-agent');

    foreach ($headers as $name => $value) {
        $request->headers->set($name, $value);
    }

    return $request;
}

// ---------------------------------------------------------------------------
// fromRequest
// ---------------------------------------------------------------------------

it('creates an instance via fromRequest', function () {
    $detect = DetectAgent::fromRequest(makeAgentRequest('GPTBot/1.0'));

    expect($detect)->toBeInstanceOf(DetectAgent::class);
});

// ---------------------------------------------------------------------------
// detect()
// ---------------------------------------------------------------------------

describe('detect', function () {
    it('returns HUMAN for an unrecognised user agent', function () {
        expect(DetectAgent::fromRequest(makeAgentRequest('Mozilla/5.0 (Windows NT 10.0)'))->detect())
            ->toBe(AgentType::HUMAN);
    });

    it('returns HUMAN when the user agent is absent', function () {
        expect(DetectAgent::fromRequest(makeRequestWithoutUA())->detect())
            ->toBe(AgentType::HUMAN);
    });

    it('returns HUMAN when the user agent is an empty string', function () {
        expect(DetectAgent::fromRequest(makeAgentRequest(''))->detect())
            ->toBe(AgentType::HUMAN);
    });

    it('returns the agent type for a known bot', function () {
        $detect = DetectAgent::fromRequest(makeAgentRequest('GPTBot/1.0'));

        expect($detect->detect())->toBe(AgentType::CRAWLER);
        expect($detect->agentName())->toBe('GPTBot');
    });

    it('memoizes a positive detection result', function () {
        $detect = DetectAgent::fromRequest(makeAgentRequest('GPTBot/1.0'));

        expect($detect->detect())->toBe($detect->detect())->toBe(AgentType::CRAWLER);
        expect($detect->agentName())->toBe('GPTBot');
    });

    it('memoizes a HUMAN detection result', function () {
        $detect = DetectAgent::fromRequest(makeAgentRequest('Mozilla/5.0'));

        expect($detect->detect())->toBe(AgentType::HUMAN);
        expect($detect->detect())->toBe(AgentType::HUMAN);
    });

    it('prioritises Cloudflare header detection over user-agent matching', function () {
        $request = makeAgentRequest('GPTBot/1.0', ['cf-brapi-request-id' => 'abc123']);
        $detect = DetectAgent::fromRequest($request);

        expect($detect->detect())->toBe(AgentType::TOOL);
        expect($detect->agentName())->toBe('CloudflareBrowserRendering');
    });

    it('prioritises AI assistants over AI crawlers', function () {
        // ChatGPT-User lives in $aiAssistants; if the crawler list were checked
        // first the name returned would differ.
        $detect = DetectAgent::fromRequest(makeAgentRequest('ChatGPT-User/1.0'));

        expect($detect->detect())->toBe(AgentType::ASSISTANT);
        expect($detect->agentName())->toBe('ChatGPT');
        expect($detect->isAiAssistant())->toBeTrue();
        expect($detect->isAiCrawler())->toBeFalse();
    });

    it('prioritises AI crawlers over AI tools', function () {
        // PerplexityBot is a crawler; PerplexityBot (the value) is not in $aiTools.
        $detect = DetectAgent::fromRequest(makeAgentRequest('PerplexityBot/1.0'));

        expect($detect->detect())->toBe(AgentType::CRAWLER);
        expect($detect->agentName())->toBe('PerplexityBot');
        expect($detect->isAiCrawler())->toBeTrue();
        expect($detect->isAiTool())->toBeFalse();
    });

    it('matching is case-insensitive', function () {
        $detect = DetectAgent::fromRequest(makeAgentRequest('gptbot/1.0'));

        expect($detect->detect())->toBe(AgentType::CRAWLER);
        expect($detect->agentName())->toBe('GPTBot');
    });
});

// ---------------------------------------------------------------------------
// isAgent()
// ---------------------------------------------------------------------------

describe('isAgent', function () {
    it('returns true when an AI agent is detected', function () {
        expect(DetectAgent::fromRequest(makeAgentRequest('GPTBot/1.0'))->isAgent())
            ->toBeTrue();
    });

    it('returns true when a Cloudflare header is present', function () {
        $request = makeRequestWithoutUA(['cf-brapi-request-id' => 'abc']);

        expect(DetectAgent::fromRequest($request)->isAgent())
            ->toBeTrue();
    });

    it('returns false for regular browsers', function () {
        expect(DetectAgent::fromRequest(makeAgentRequest('Mozilla/5.0 (compatible)'))->isAgent())
            ->toBeFalse();
    });

    it('returns false for an empty user agent', function () {
        expect(DetectAgent::fromRequest(makeAgentRequest(''))->isAgent())
            ->toBeFalse();
    });

    it('returns false when there is no user agent header', function () {
        expect(DetectAgent::fromRequest(makeRequestWithoutUA())->isAgent())
            ->toBeFalse();
    });
});

// ---------------------------------------------------------------------------
// isCloudflareBrowserRendering()
// ---------------------------------------------------------------------------

describe('isCloudflareBrowserRendering', function () {
    it('detects the cf-brapi-request-id header', function () {
        $request = makeRequestWithoutUA(['cf-brapi-request-id' => 'abc123']);

        expect(DetectAgent::fromRequest($request)->isCloudflareBrowserRendering())
            ->toBeTrue();
    });

    it('detects the cf-brapi-devtools header', function () {
        $request = makeRequestWithoutUA(['cf-brapi-devtools' => '1']);

        expect(DetectAgent::fromRequest($request)->isCloudflareBrowserRendering())
            ->toBeTrue();
    });

    it('detects the cf-biso-devtools header', function () {
        $request = makeRequestWithoutUA(['cf-biso-devtools' => '1']);

        expect(DetectAgent::fromRequest($request)->isCloudflareBrowserRendering())
            ->toBeTrue();
    });

    it('detects the Signature-agent header', function () {
        $request = makeRequestWithoutUA(['Signature-agent' => '"https://bot.example.com"']);

        expect(DetectAgent::fromRequest($request)->isCloudflareBrowserRendering())
            ->toBeTrue();
    });

    it('returns false without any Cloudflare headers', function () {
        expect(DetectAgent::fromRequest(makeAgentRequest('Mozilla/5.0'))->isCloudflareBrowserRendering())
            ->toBeFalse();
    });

    it('returns false for unrelated custom headers', function () {
        $request = makeRequestWithoutUA(['X-Custom-Header' => 'value']);

        expect(DetectAgent::fromRequest($request)->isCloudflareBrowserRendering())
            ->toBeFalse();
    });
});

// ---------------------------------------------------------------------------
// isAiAssistant()
// ---------------------------------------------------------------------------

describe('isAiAssistant', function () {
    it('returns false for regular browsers', function () {
        expect(DetectAgent::fromRequest(makeAgentRequest('Mozilla/5.0'))->isAiAssistant())
            ->toBeFalse();
    });

    it('returns false for AI crawlers', function () {
        expect(DetectAgent::fromRequest(makeAgentRequest('GPTBot/1.0'))->isAiAssistant())
            ->toBeFalse();
    });

    it('returns false for AI tools', function () {
        expect(DetectAgent::fromRequest(makeAgentRequest('Diffbot/1.0'))->isAiAssistant())
            ->toBeFalse();
    });

    it('detects known AI assistants', function (string $ua, string $expectedName) {
        $detect = DetectAgent::fromRequest(makeAgentRequest($ua));

        expect($detect->isAiAssistant())->toBeTrue()
            ->and($detect->detect())->toBe(AgentType::ASSISTANT)
            ->and($detect->agentName())->toBe($expectedName);
    })->with([
        'ChatGPT-User' => ['ChatGPT-User/1.0',          'ChatGPT'],
        'ChatGPT-Agent' => ['ChatGPT-Agent/1.0',         'ChatGPT'],
        'ChatGPT Agent (space)' => ['ChatGPT Agent/1.0',         'ChatGPT'],
        'Operator' => ['Operator/1.0',              'Operator'],
        'NovaAct' => ['NovaAct/1.0',               'NovaAct'],
        'Claude-User' => ['Claude-User/1.0',           'Claude'],
        'Claude-Web' => ['Claude-Web/1.0',            'Claude'],
        'Gemini-Deep-Research' => ['Gemini-Deep-Research/1.0',  'Gemini'],
        'GoogleAgent-Mariner' => ['GoogleAgent-Mariner/1.0',   'Gemini'],
        'NotebookLM' => ['NotebookLM/1.0',            'NotebookLM'],
        'Google-NotebookLM' => ['Google-NotebookLM/1.0',     'NotebookLM'],
        'Perplexity-User' => ['Perplexity-User/1.0',       'Perplexity'],
        'Manus-User' => ['Manus-User/1.0',            'Manus'],
        'MistralAI-User' => ['MistralAI-User/1.0',        'MistralAI'],
        'Devin' => ['Devin/1.0',                 'Devin'],
        'kagi-fetcher' => ['kagi-fetcher/1.0',          'Kagi'],
        'Andibot' => ['Andibot/1.0',               'Andibot'],
        'TwinAgent' => ['TwinAgent/1.0',             'TwinAgent'],
    ]);
});

// ---------------------------------------------------------------------------
// isAiCrawler()
// ---------------------------------------------------------------------------

describe('isAiCrawler', function () {
    it('returns false for regular browsers', function () {
        expect(DetectAgent::fromRequest(makeAgentRequest('Mozilla/5.0'))->isAiCrawler())
            ->toBeFalse();
    });

    it('returns false for AI assistants', function () {
        expect(DetectAgent::fromRequest(makeAgentRequest('ChatGPT-User/1.0'))->isAiCrawler())
            ->toBeFalse();
    });

    it('detects known AI crawlers', function (string $ua, string $expectedName) {
        $detect = DetectAgent::fromRequest(makeAgentRequest($ua));

        expect($detect->isAiCrawler())->toBeTrue()
            ->and($detect->detect())->toBe(AgentType::CRAWLER)
            ->and($detect->agentName())->toBe($expectedName);
    })->with([
        'GPTBot' => ['GPTBot/1.0',                 'GPTBot'],
        'OAI-SearchBot' => ['OAI-SearchBot/1.0',          'OAISearchBot'],
        'OpenAI' => ['OpenAI/1.0',                 'OpenAI'],
        'ClaudeBot' => ['ClaudeBot/1.0',              'Anthropic'],
        'anthropic-ai' => ['anthropic-ai/1.0',           'Anthropic'],
        'Claude-SearchBot' => ['Claude-SearchBot/1.0',       'Anthropic'],
        'Amazonbot' => ['Amazonbot/0.1',              'Amazon'],
        'amazon-kendra' => ['amazon-kendra/1.0',          'Amazon'],
        'bedrockbot' => ['bedrockbot/1.0',             'Amazon'],
        'Google-Extended' => ['Google-Extended/1.0',        'Google'],
        'GoogleOther' => ['GoogleOther/1.0',            'Google'],
        'CloudVertexBot' => ['CloudVertexBot/1.0',         'Google'],
        'FacebookBot' => ['FacebookBot/1.0',            'Meta'],
        'facebookexternalhit' => ['facebookexternalhit/1.1',    'Meta'],
        'CCBot' => ['CCBot/2.0',                  'CCBot'],
        'Bytespider' => ['Bytespider/1.0',             'Bytespider'],
        'TikTokSpider' => ['TikTokSpider/1.0',           'TikTokSpider'],
        'PetalBot' => ['PetalBot/1.0',               'PetalBot'],
        'PanguBot' => ['PanguBot/1.0',               'PanguBot'],
        'Applebot' => ['Applebot/0.1',               'Applebot'],
        'Applebot-Extended' => ['Applebot-Extended/1.0',      'Applebot'],
        'YouBot' => ['YouBot/1.0',                 'YouBot'],
        'DuckAssistBot' => ['DuckAssistBot/1.0',          'DuckAssistBot'],
        'YandexAdditionalBot' => ['YandexAdditionalBot/1.0',    'Yandex'],
        'AzureAI-SearchBot' => ['AzureAI-SearchBot/1.0',      'AzureAI'],
        'Cloudflare-AutoRAG' => ['Cloudflare-AutoRAG/1.0',     'Cloudflare'],
        'Bravebot' => ['Bravebot/1.0',               'Bravebot'],
        'PhindBot' => ['PhindBot/1.0',               'PhindBot'],
        'PerplexityBot' => ['PerplexityBot/1.0',          'PerplexityBot'],
        'iAskBot' => ['iAskBot/1.0',                'iAsk'],
        'iaskspider' => ['iaskspider/1.0',             'iAsk'],
        'SBIntuitionsBot' => ['SBIntuitionsBot/1.0',        'SBIntuitions'],
        'DeepSeekBot' => ['DeepSeekBot/1.0',            'DeepSeek'],
        'ChatGLM-Spider' => ['ChatGLM-Spider/1.0',         'ChatGLM'],
        'AI2Bot' => ['AI2Bot/1.0',                 'AI2Bot'],
        'Ai2Bot-Dolma' => ['Ai2Bot-Dolma/1.0',           'AI2Bot'],
        'LAIONDownloader' => ['LAIONDownloader/1.0',        'LAION'],
        'img2dataset' => ['img2dataset/1.0',            'img2dataset'],
    ]);
});

// ---------------------------------------------------------------------------
// isAiTool()
// ---------------------------------------------------------------------------

describe('isAiTool', function () {
    it('returns false for regular browsers', function () {
        expect(DetectAgent::fromRequest(makeAgentRequest('Mozilla/5.0'))->isAiTool())
            ->toBeFalse();
    });

    it('returns false for AI crawlers', function () {
        expect(DetectAgent::fromRequest(makeAgentRequest('GPTBot/1.0'))->isAiTool())
            ->toBeFalse();
    });

    it('detects known AI tools', function (string $ua, string $expectedName) {
        $detect = DetectAgent::fromRequest(makeAgentRequest($ua));

        expect($detect->isAiTool())->toBeTrue()
            ->and($detect->detect())->toBe(AgentType::TOOL)
            ->and($detect->agentName())->toBe($expectedName);
    })->with([
        'Diffbot' => ['Diffbot/1.0',             'Diffbot'],
        'FirecrawlAgent' => ['FirecrawlAgent/1.0',      'Firecrawl'],
        'Crawl4AI' => ['Crawl4AI/1.0',            'Crawl4AI'],
        'Scrapy' => ['Scrapy/1.0',              'Scrapy'],
        'FriendlyCrawler' => ['FriendlyCrawler/1.0',     'FriendlyCrawler'],
        'TavilyBot' => ['TavilyBot/1.0',           'Tavily'],
        'LinkupBot' => ['LinkupBot/1.0',           'Linkup'],
        'omgilibot' => ['omgilibot/1.0',           'Omgili'],
        'SemrushBot-OCOB' => ['SemrushBot-OCOB/1.0',    'Semrush'],
        'SemrushBot-SWA' => ['SemrushBot-SWA/1.0',     'Semrush'],
        'KlaviyoAIBot' => ['KlaviyoAIBot/1.0',        'KlaviyoAI'],
        'LinerBot' => ['LinerBot/1.0',            'LinerBot'],
        'QuillBot' => ['QuillBot/1.0',            'QuillBot'],
        'AddSearchBot' => ['AddSearchBot/1.0',        'AddSearch'],
        'aiHitBot' => ['aiHitBot/1.0',            'aiHitBot'],
    ]);
});
