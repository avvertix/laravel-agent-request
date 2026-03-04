<?php

declare(strict_types=1);

namespace Avvertix\AgentRequest\LaravelAgentRequest\Concern;

use Avvertix\AgentRequest\LaravelAgentRequest\Actions\DetectAgent;
use Illuminate\Http\Request;
use InvalidArgumentException;

trait InteractWithDetector
{
    private ?DetectAgent $detector = null;

    private function detector(?Request $request = null): DetectAgent
    {
        return $this->detector ??= self::getDetectorClass()::fromRequest($request ?? $this);
    }

    public static function getDetectorClass(): string
    {
        $actionClass = config('agent-request.detector');

        if (blank($actionClass)) {
            throw new InvalidArgumentException('No detector class specified in agent-request configuration.');
        }

        if (! class_exists($actionClass)) {
            throw new InvalidArgumentException("The configured detector class in agent-request [{$actionClass}] does not exists.");
        }

        if (! is_a($actionClass, DetectAgent::class, true)) {
            throw new InvalidArgumentException("The configured action class in agent-request [{$actionClass}] must extend DetectAgent.");
        }

        return $actionClass;
    }
}
