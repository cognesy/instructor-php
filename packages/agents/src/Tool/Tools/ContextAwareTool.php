<?php declare(strict_types=1);

namespace Cognesy\Agents\Tool\Tools;

use Cognesy\Agents\Tool\Contracts\CanAccessToolCall;
use Cognesy\Agents\Tool\Traits\HasToolCall;

abstract class ContextAwareTool extends StateAwareTool implements CanAccessToolCall
{
    use HasToolCall;
}
