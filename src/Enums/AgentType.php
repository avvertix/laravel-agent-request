<?php

declare(strict_types=1);

namespace Avvertix\AgentRequest\LaravelAgentRequest\Enums;

enum AgentType: string
{
    case HUMAN = 'human';
    case ASSISTANT = 'assistant';
    case CRAWLER = 'crawler';
    case TOOL = 'tool';
}
