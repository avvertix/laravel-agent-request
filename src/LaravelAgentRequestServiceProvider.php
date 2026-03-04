<?php

namespace Avvertix\AgentRequest\LaravelAgentRequest;

use Avvertix\AgentRequest\LaravelAgentRequest\Actions\DetectAgent;
use Avvertix\AgentRequest\LaravelAgentRequest\Commands\GenerateRobotsTxtCommand;
use Avvertix\AgentRequest\LaravelAgentRequest\Http\AgentRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelAgentRequestServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-agent-request')
            ->hasConfigFile()
            ->hasCommand(GenerateRobotsTxtCommand::class);
    }

    public function packageRegistered(): void
    {
        $detectorClass = config('agent-request.detector', DetectAgent::class);

        $this->app->scoped(DetectAgent::class, fn ($app) => $detectorClass::fromRequest($app['request']));

        $this->app->scoped(AgentRequest::class, fn ($app) => AgentRequest::createFrom($app['request']));
    }

    public function packageBooted(): void
    {
        /**
         * Determine if the current request is asking for Markdown.
         *
         * Checks whether the first entry in the Accept header is a Markdown
         * MIME type (text/markdown per RFC 7763, or the legacy text/x-markdown).
         */
        Request::macro('wantsMarkdown', function (): bool {
            $acceptable = $this->getAcceptableContentTypes();

            return isset($acceptable[0]) && Str::contains(strtolower($acceptable[0]), ['text/markdown', 'text/x-markdown']);
        });

        /**
         * Determine if the current request probably expects a Markdown response.
         *
         * Returns true when the Accept header explicitly advertises a Markdown MIME type
         * (via wantsMarkdown()) or when it accepts plain text (text/plain), since
         * plain-text clients can generally render Markdown content.
         */
        Request::macro('expectsMarkdown', function (): bool {
            return $this->accepts(['text/markdown', 'text/x-markdown']) || $this->accepts('text/plain');
        });

        /**
         * Determine if the request is probably coming from an Agent or a Bot of any kind
         */
        Request::macro('isAgent', function (): bool {
            return app(DetectAgent::class)->isAgent();
        });

        /**
         * Determine if the request is probably coming from a known AI Agent of type assistant, e.g. Claude Code, ChatGPT, ...
         */
        Request::macro('isAiAssistant', function (): bool {
            return app(DetectAgent::class)->isAiAssistant();
        });
    }
}
