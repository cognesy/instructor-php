<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Data\Collections;

use Cognesy\Addons\Core\StateProcessors;
use Cognesy\Addons\ToolUse\Contracts\CanProcessToolState;
use Cognesy\Addons\ToolUse\Data\ToolUseState;

/**
 * @deprecated
 */
final readonly class ToolUseStateProcessors extends StateProcessors
{
    public function __construct(CanProcessToolState ...$processors) {
        parent::__construct(...$processors);
    }

//    public function withProcessors(CanProcessToolState ...$processors): static {
//        return new static(...$processors);
//    }

    protected function doProcess(object $processor, object $state, callable $next): object {
        /** @var CanProcessToolState $processor */
        /** @var ToolUseState $state */
        return $processor->process($state, $next);
    }
}

