<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Pi\Domain\Enum;

/**
 * Thinking level for Pi CLI (maps to --thinking flag)
 */
enum ThinkingLevel: string
{
    case Off = 'off';
    case Minimal = 'minimal';
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case ExtraHigh = 'xhigh';
}
