<?php

declare(strict_types=1);

namespace Avvertix\AgentRequest\LaravelAgentRequest\Actions;

use Avvertix\AgentRequest\LaravelAgentRequest\Enums\AgentType;
use Illuminate\Http\Request;

class DetectAgent
{
    /**
     * AI assistant agents that interact with websites directly on behalf of a human user.
     * These are agents that browse, research, or act for a specific person in real time.
     *
     * @var array<string, string>
     */
    protected static array $aiAssistants = [
        // OpenAI
        'ChatGPT' => 'ChatGPT[-\s]?(User|Agent)',
        'Operator' => '\bOperator\b',
        'NovaAct' => 'NovaAct',

        // Anthropic
        'Claude' => 'Claude[-\s]?(User|Web)',

        // Google
        'Gemini' => 'Gemini[-\s]?Deep[-\s]?Research|GoogleAgent[-\s]?Mariner',
        'NotebookLM' => 'NotebookLM|Google[-\s]?NotebookLM',

        // Perplexity
        'Perplexity' => 'Perplexity[-\s]?User',

        // Manus
        'Manus' => 'Manus[-\s]?User',

        // Mistral
        'MistralAI' => 'MistralAI[-\s]?User',

        // Cognition
        'Devin' => '\bDevin\b',

        // Kagi
        'Kagi' => 'kagi[-\s]?fetcher',

        // Andi
        'Andibot' => 'Andibot',

        // Twin
        'TwinAgent' => 'TwinAgent',
    ];

    /**
     * AI training crawlers and indexing bots that collect data for AI systems.
     * These are automated crawlers that do not represent a live human user.
     *
     * @var array<string, string>
     */
    protected static array $aiCrawlers = [
        // OpenAI
        'GPTBot' => 'GPTBot',
        'OAISearchBot' => 'OAI[-\s]?SearchBot',
        'OpenAI' => '\bOpenAI\b',

        // Anthropic
        'Anthropic' => 'ClaudeBot|anthropic[-\s]?ai|Claude[-\s]?SearchBot',

        // Amazon / AWS
        'Amazon' => 'Amazonbot|amazon[-\s]?kendra|AmazonBuyForMe|Amzn[-\s]?(SearchBot|User)|bedrockbot',

        // Google
        'Google' => 'Google[-\s]?(Extended|CloudVertexBot|Firebase)|CloudVertexBot|GoogleOther([-\s]?(Image|Video))?',

        // Meta
        'Meta' => 'meta[-\s]?(externalagent|externalfetcher|webindexer)|Meta[-\s]?(ExternalAgent|ExternalFetcher)|FacebookBot|facebookexternalhit',

        // Cohere
        'Cohere' => 'cohere[-\s]?ai|cohere[-\s]?training[-\s]?data[-\s]?crawler',

        // Common Crawl
        'CCBot' => 'CCBot',

        // ByteDance
        'Bytespider' => 'Bytespider',
        'TikTokSpider' => 'TikTokSpider',

        // Huawei
        'PetalBot' => 'PetalBot',
        'PanguBot' => 'PanguBot',

        // Apple
        'Applebot' => 'Applebot([-\s]?Extended)?',

        // You.com
        'YouBot' => 'YouBot',

        // DuckDuckGo
        'DuckAssistBot' => 'DuckAssistBot',

        // Yandex
        'Yandex' => 'YandexAdditional(Bot)?',

        // Microsoft / Azure
        'AzureAI' => 'AzureAI[-\s]?SearchBot',

        // Cloudflare
        'Cloudflare' => 'Cloudflare[-\s]?AutoRAG',

        // Brave
        'Bravebot' => 'Bravebot',

        // Phind
        'PhindBot' => 'PhindBot',

        // Perplexity crawler
        'PerplexityBot' => 'PerplexityBot',

        // iAsk
        'iAsk' => 'iAskBot|iaskspider',

        // SBIntuitions
        'SBIntuitions' => 'SBIntuitionsBot',

        // DeepSeek
        'DeepSeek' => 'DeepSeekBot',

        // Zhipu AI
        'ChatGLM' => 'ChatGLM[-\s]?Spider',

        // Allen Institute for AI
        'AI2Bot' => 'AI2Bot|Ai2Bot[-\s]?Dolma|AI2Bot[-\s]?DeepResearchEval',

        // LAION
        'LAION' => 'LAIONDownloader|laion[-\s]?huggingface[-\s]?processor',

        // Generic AI dataset tool
        'img2dataset' => 'img2dataset',
    ];

    /**
     * AI-powered research, analysis, and data-extraction tools.
     * Includes both dedicated AI tools and crawlers commonly used in AI data pipelines.
     *
     * @var array<string, string>
     */
    protected static array $aiTools = [
        // Structured data extraction
        'Diffbot' => 'Diffbot',

        // Web scraping / crawling frameworks built for AI
        'Firecrawl' => 'FirecrawlAgent',
        'Crawl4AI' => 'Crawl4AI',
        'Scrapy' => 'Scrapy',
        'FriendlyCrawler' => 'FriendlyCrawler',
        'Crawlspace' => 'Crawlspace',

        // AI search / research APIs
        'Tavily' => 'TavilyBot',
        'Linkup' => 'LinkupBot',

        // Content intelligence / marketing
        'Omgili' => 'omgili(bot)?',
        'Webzio' => 'Webzio[-\s]?Extended|webzio[-\s]?extended',
        'Semrush' => 'SemrushBot[-\s]?(OCOB|SWA)',
        'Awario' => 'Awario',
        'BuddyBot' => 'BuddyBot',
        'Brightbot' => 'Brightbot',
        'KlaviyoAI' => 'KlaviyoAIBot',
        'LinerBot' => 'LinerBot',
        'Linguee' => 'Linguee[\s]?Bot',
        'EchoboxBot' => 'EchoboxBot',
        'Echobot' => 'Echobot',
        'Sidetrade' => 'Sidetrade[\s]?indexer[\s]?bot',
        'WRTNBot' => 'WRTNBot',
        'QuillBot' => 'QuillBot|quillbot\.com',

        // SEO / analytics crawlers used with AI
        'Panscient' => 'Panscient|panscient\.com',
        'QualifiedBot' => 'QualifiedBot',
        'ShapBot' => 'ShapBot',
        'AddSearch' => 'AddSearchBot',
        'Atlassian' => 'atlassian[-\s]?bot',

        // Research / academic
        'ICC' => 'ICC[-\s]?Crawler',
        'ISSCyberRisk' => 'ISSCyberRiskCrawler',
        'Poggio' => 'Poggio[-\s]?Citations',
        'Poseidon' => 'Poseidon[\s]?Research[\s]?Crawler',
        'VelenPublic' => 'VelenPublicWebCrawler',
        'Timpibot' => 'Timpibot',

        // Financial / business intelligence
        'Factset' => 'Factset_spyderbot',

        // Miscellaneous AI-adjacent bots
        'aiHitBot' => 'aiHitBot',
        'Anomura' => 'Anomura',
        'Bigsur' => 'bigsur\.ai',
        'Channel3' => 'Channel3Bot',
        'Cotoyogi' => 'Cotoyogi',
        'Datenbank' => 'Datenbank[\s]?Crawler',
        'IbouBot' => 'IbouBot',
        'ImagesiftBot' => 'ImagesiftBot',
        'imageSpider' => 'imageSpider',
        'KunatoCrawler' => 'KunatoCrawler',
        'KangarooBot' => 'Kangaroo[\s]?Bot',
        'LCC' => '\bLCC\b',
        'MyCentral' => 'MyCentralAIScraperBot',
        'netEstate' => 'netEstate[\s]?Imprint[\s]?Crawler',
        'TerraCotta' => 'TerraCotta',
        'Thinkbot' => 'Thinkbot',
        'WARDBot' => 'WARDBot',
        'wpbot' => 'wpbot',
        'YaK' => '\bYaK\b',
        'YandexAdditional' => 'YandexAdditionalBot',
        'ZanistaBot' => 'ZanistaBot',
    ];

    /**
     * Cloudflare Browser Rendering headers that are automatically attached to
     * every request and cannot be removed or overridden by the caller.
     *
     * Presence of any one of these headers constitutes verified evidence that the
     * request originated from Cloudflare's rendering infrastructure.
     *
     * @see https://developers.cloudflare.com/browser-rendering/reference/automatic-request-headers/
     *
     * @var list<string>
     */
    protected static array $cloudflareBrowserRenderingHeaders = [
        // Unique ID attached to REST API Browser Rendering requests.
        'cf-brapi-request-id',
        // Unique ID attached to Workers Bindings Browser Rendering requests.
        'cf-brapi-devtools',
        // Flag indicating the request originated from Cloudflare's rendering infrastructure.
        'cf-biso-devtools',
        // Location of the bot's public keys used to sign requests via Web Bot Auth.
        // @see https://datatracker.ietf.org/doc/html/draft-meunier-web-bot-auth-architecture
        'Signature-agent',
    ];

    /**
     * Canonical User-Agent names for use in robots.txt Disallow rules,
     * grouped by agent category. Keys match AgentType backed values.
     *
     * @var array<string, list<string>>
     */
    public static array $robotsUserAgents = [
        'assistant' => [
            'ChatGPT-User', 'Operator', 'NovaAct', 'Claude-User', 'Claude-Web',
            'Gemini-Deep-Research', 'GoogleAgent-Mariner', 'NotebookLM',
            'Perplexity-User', 'Manus-User', 'MistralAI-User', 'Devin',
            'kagi-fetcher', 'Andibot', 'TwinAgent',
        ],
        'crawler' => [
            'GPTBot', 'OAI-SearchBot', 'ClaudeBot', 'anthropic-ai', 'Claude-SearchBot',
            'Amazonbot', 'Google-Extended', 'GoogleOther', 'CloudVertexBot',
            'FacebookBot', 'CCBot', 'Bytespider', 'TikTokSpider', 'PetalBot', 'PanguBot',
            'Applebot', 'YouBot', 'DuckAssistBot', 'YandexAdditionalBot',
            'AzureAI-SearchBot', 'Cloudflare-AutoRAG', 'Bravebot', 'PhindBot',
            'PerplexityBot', 'iAskBot', 'SBIntuitionsBot', 'DeepSeekBot',
            'ChatGLM-Spider', 'AI2Bot', 'LAIONDownloader', 'img2dataset',
        ],
        'tool' => [
            'Diffbot', 'FirecrawlAgent', 'Crawl4AI', 'Scrapy', 'TavilyBot',
            'LinkupBot', 'omgilibot', 'SemrushBot-OCOB', 'SemrushBot-SWA',
        ],
    ];

    private bool $detected = false;

    private ?AgentType $cachedType = null;

    private ?string $cachedName = null;

    public function __construct(protected readonly Request $request) {}

    /**
     * Create a new instance from the given request.
     */
    public static function fromRequest(Request $request): self
    {
        return new self($request);
    }

    /**
     * Determine if the request is likely from an AI agent of any kind.
     */
    public function isAgent(): bool
    {
        return $this->detect() !== AgentType::HUMAN;
    }

    /**
     * Determine if the request originated from Cloudflare Browser Rendering.
     *
     * Detection is based on the presence of proprietary headers that Cloudflare
     * automatically attaches and that cannot be removed or overridden by the caller.
     * This is a stronger signal than User-Agent matching because the headers are
     * injected by Cloudflare's infrastructure, not self-reported by the client.
     *
     * Requests that carry a `Signature-agent` header may additionally be verified
     * via Web Bot Auth cryptographic signatures (`Signature` / `Signature-Input`).
     */
    public function isCloudflareBrowserRendering(): bool
    {
        return $this->detectCloudflareBrowserRendering() !== null;
    }

    /**
     * Determine if the request is from a user-facing AI assistant
     * (an agent browsing on behalf of a live human user).
     */
    public function isAiAssistant(): bool
    {
        return $this->matchPatterns(static::$aiAssistants) !== null;
    }

    /**
     * Determine if the request is from an AI training or indexing crawler.
     */
    public function isAiCrawler(): bool
    {
        return $this->matchPatterns(static::$aiCrawlers) !== null;
    }

    /**
     * Determine if the request is from an AI-powered research or data-extraction tool.
     */
    public function isAiTool(): bool
    {
        return $this->matchPatterns(static::$aiTools) !== null;
    }

    /**
     * Return the AgentType of the detected agent, or AgentType::HUMAN if none matched.
     *
     * Cloudflare Browser Rendering is checked first because its proprietary headers
     * are injected by infrastructure and cannot be spoofed by the requesting client,
     * making them a stronger signal than User-Agent pattern matching.
     *
     * The result is memoized for the lifetime of this instance.
     */
    public function detect(): AgentType
    {
        if ($this->detected) {
            return $this->cachedType ?? AgentType::HUMAN;
        }

        $this->detected = true;

        if ($this->detectCloudflareBrowserRendering() !== null) {
            $this->cachedType = AgentType::TOOL;
            $this->cachedName = 'CloudflareBrowserRendering';
        } elseif ($name = $this->matchPatterns(static::$aiAssistants)) {
            $this->cachedType = AgentType::ASSISTANT;
            $this->cachedName = $name;
        } elseif ($name = $this->matchPatterns(static::$aiCrawlers)) {
            $this->cachedType = AgentType::CRAWLER;
            $this->cachedName = $name;
        } elseif ($name = $this->matchPatterns(static::$aiTools)) {
            $this->cachedType = AgentType::TOOL;
            $this->cachedName = $name;
        }

        return $this->cachedType ?? AgentType::HUMAN;
    }

    /**
     * Return the display-name key of the first matched agent, or null if none matched.
     *
     * The result is memoized for the lifetime of this instance.
     */
    public function agentName(): ?string
    {
        $this->detect();

        return $this->cachedName;
    }

    /**
     * Check for the presence of Cloudflare Browser Rendering proprietary headers.
     * Returns the matched header name, or null if none are present.
     */
    protected function detectCloudflareBrowserRendering(): ?string
    {
        foreach (static::$cloudflareBrowserRenderingHeaders as $header) {
            if ($this->request->hasHeader($header)) {
                return 'CloudflareBrowserRendering';
            }
        }

        return null;
    }

    /**
     * Match the request User-Agent against the given name → pattern map.
     * Returns the first matching name key, or null if none matched.
     *
     * @param  array<string, string>  $patterns
     */
    protected function matchPatterns(array $patterns): ?string
    {
        $userAgent = $this->request->userAgent();

        if ($userAgent === null || $userAgent === '') {
            return null;
        }

        foreach ($patterns as $name => $pattern) {
            if (preg_match('/'.$pattern.'/i', $userAgent) === 1) {
                return $name;
            }
        }

        return null;
    }
}
