<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\ClaudeCode\Domain\Enum;

enum InputFormat: string
{
    case Text = 'text';
    case StreamJson = 'stream-json';
}
