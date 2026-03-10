<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Pi\Domain\Enum;

/**
 * Output mode for Pi CLI
 */
enum OutputMode: string
{
    /** Raw JSONL events for programmatic processing */
    case Json = 'json';

    /** RPC mode for process integration */
    case Rpc = 'rpc';
}
