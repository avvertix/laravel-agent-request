<?php

declare(strict_types=1);

namespace Avvertix\AgentRequest\LaravelAgentRequest\Http;

use Avvertix\AgentRequest\LaravelAgentRequest\Actions\DetectAgent;
use Avvertix\AgentRequest\LaravelAgentRequest\Concern\InteractWithDetector;
use Avvertix\AgentRequest\LaravelAgentRequest\Enums\AgentType;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AgentRequest extends Request
{
    use InteractWithDetector;

    public function isAgent(): bool
    {
        return $this->detector()->isAgent();
    }

    public function isAiAssistant(): bool
    {
        return $this->detector()->isAiAssistant();
    }

    public function isAiCrawler(): bool
    {
        return $this->detector()->isAiCrawler();
    }

    public function isAiTool(): bool
    {
        return $this->detector()->isAiTool();
    }

    public function isCloudflareBrowserRendering(): bool
    {
        return $this->detector()->isCloudflareBrowserRendering();
    }

    public function detect(): AgentType
    {
        return $this->detector()->detect();
    }

    public function agentName(): ?string
    {
        return $this->detector()->agentName();
    }

    public function wantsMarkdown(): bool
    {
        $acceptable = $this->getAcceptableContentTypes();

        return isset($acceptable[0])
            && Str::contains(strtolower($acceptable[0]), ['text/markdown', 'text/x-markdown']);
    }

    public function expectsMarkdown(): bool
    {
        return $this->wantsMarkdown() || $this->accepts('text/plain');
    }
}
