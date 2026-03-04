# Laravel Agent Request

[![Latest Version on Packagist](https://img.shields.io/packagist/v/avvertix/laravel-agent-request.svg?style=flat-square)](https://packagist.org/packages/avvertix/laravel-agent-request)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/avvertix/laravel-agent-request/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/avvertix/laravel-agent-request/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/avvertix/laravel-agent-request/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/avvertix/laravel-agent-request/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/avvertix/laravel-agent-request.svg?style=flat-square)](https://packagist.org/packages/avvertix/laravel-agent-request)

Detect and manage AI agent HTTP requests in Laravel applications. It identifies AI assistants, crawlers, and data-extraction tools via User-Agent patterns and infrastructure headers, then gives you middleware to block, trace, or serve them differently.

## Requirements

- PHP 8.3 or higher


## Installation

Install the package via Composer:

```bash
composer require avvertix/laravel-agent-request
```

The service provider is auto-discovered by Laravel.


## Configuration

Agent Request is configurable via environment variables.


| Variable | Default | Description |
|---|---|---|
| `AGENT_REQUEST_ENABLED` | `true` | Set to `false` to bypass all middleware globally |
| `AGENT_REQUEST_BLOCK` | — | Comma-separated list of categories to block, e.g. `assistant,crawler,tool` |


In case you want to configure the detection logic you can publish the configuration file:

```bash
php artisan vendor:publish --tag="laravel-agent-request-config"
```


### Environment variables

## Agent categories

We groups known AI agents into three categories:

| Category | `AgentType` | Description |
|---|---|---|
| Assistants | `AgentType::ASSISTANT` | Agents browsing on behalf of a live user (ChatGPT-User, Claude-User, Gemini, Perplexity-User, …) |
| Crawlers | `AgentType::CRAWLER` | Automated training and indexing bots (GPTBot, ClaudeBot, Google-Extended, Applebot, …) |
| Tools | `AgentType::TOOL` | AI-powered research and data-extraction tools (Diffbot, FirecrawlAgent, Scrapy, TavilyBot, …) |

Requests that match no known pattern return `AgentType::HUMAN`.

## Usage

### Detecting agents in a controller

Inject or type-hint `AgentRequest` instead of the standard `Request`:

```php
use Avvertix\AgentRequest\LaravelAgentRequest\Http\AgentRequest;
use Avvertix\AgentRequest\LaravelAgentRequest\Enums\AgentType;

class PageController
{
    public function show(AgentRequest $request)
    {
        if ($request->isAgent()) {
            // Any AI agent
        }

        $type = $request->detect();          // AgentType::CRAWLER, ::ASSISTANT, ::TOOL, or ::HUMAN
        $name = $request->agentName();       // e.g. 'GPTBot', 'Claude', null for humans

        if ($type === AgentType::ASSISTANT) {
            // Respond differently for interactive AI assistants
        }

        if ($request->expectsMarkdown()) {
            return response($markdown, headers: ['Content-Type' => 'text/markdown']);
        }
    }
}
```

All detection methods available on `AgentRequest`:

| Method | Returns | Description |
|---|---|---|
| `isAgent()` | `bool` | Any recognised AI agent |
| `isAiAssistant()` | `bool` | User-facing AI assistant |
| `isAiCrawler()` | `bool` | Training or indexing crawler |
| `isAiTool()` | `bool` | Data-extraction or research tool |
| `isCloudflareBrowserRendering()` | `bool` | Cloudflare Browser Rendering infrastructure |
| `detect()` | `AgentType` | Category of the detected agent |
| `agentName()` | `?string` | Canonical name key of the agent, or `null` |
| `wantsMarkdown()` | `bool` | `Accept` header contains `text/markdown` or `text/x-markdown` |
| `expectsMarkdown()` | `bool` | `wantsMarkdown()` or `Accept: text/plain` |

### Middleware

Register middleware in `bootstrap/app.php` or a route group:

```php
use Avvertix\AgentRequest\LaravelAgentRequest\Http\Middleware\DenyAgentMiddleware;
use Avvertix\AgentRequest\LaravelAgentRequest\Http\Middleware\TraceAgentMiddleware;

->withMiddleware(function (Middleware $middleware) {
    $middleware->append(DenyAgentMiddleware::class);
    $middleware->append(TraceAgentMiddleware::class);
})
```

#### DenyAgentMiddleware

Returns a `404 Not Found` response for any agent whose category appears in the `agent-request.block` config list.

By default `assistant` and `tool` agents are blocked; `crawler` agents are allowed through (they respect `robots.txt`). To block crawlers too, update the config or set the environment variable:

```env
AGENT_REQUEST_BLOCK=assistant,crawler,tool
```

To disable blocking entirely without removing the middleware:

```env
AGENT_REQUEST_ENABLED=false
```

#### TraceAgentMiddleware

Adds `agent_name` and `agent_type` to the [Laravel Context](https://laravel.com/docs/context) for every recognised AI agent request. Laravel includes them in log entries automatically:

```
agent_name: GPTBot
agent_type: AgentType::CRAWLER
```

This middleware never blocks. Stack it with `DenyAgentMiddleware` freely.


### Request macros

The package adds two macros to the base `Illuminate\Http\Request`:

```php
$request->isAgent();        // bool — any AI agent
$request->isAiAssistant();  // bool — user-facing AI assistant
```

### Using DetectAgent directly

```php
use Avvertix\AgentRequest\LaravelAgentRequest\Actions\DetectAgent;

$detector = DetectAgent::fromRequest($request);

$detector->detect();      // AgentType
$detector->agentName();   // ?string
$detector->isAgent();
$detector->isAiCrawler();
```


## Generating a robots.txt

Generate a `robots.txt` that instructs known AI agents to not crawl your site:

```bash
# Write (or overwrite) public/robots.txt with all known agents
php artisan agent-request:robots-txt

# Limit to specific categories
php artisan agent-request:robots-txt --category=crawler
php artisan agent-request:robots-txt --category=assistant --category=crawler

# Append missing entries to an existing robots.txt without duplicating
php artisan agent-request:robots-txt --merge
```

`--category` accepts `assistant`, `crawler`, and `tool`. Pass it multiple times to combine. Omit it to include all three.

## Extending the detector

Extend `DetectAgent` and override the pattern arrays, then register your class in the config:

```php
use Avvertix\AgentRequest\LaravelAgentRequest\Actions\DetectAgent;

class MyDetector extends DetectAgent
{
    protected static array $aiAssistants = [
        ...parent::$aiAssistants,
        'Acme' => 'AcmeAgent',
    ];
}
```

```php
// config/agent-request.php
'detector' => MyDetector::class,
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Alessio Vertemati](https://github.com/avvertix)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
