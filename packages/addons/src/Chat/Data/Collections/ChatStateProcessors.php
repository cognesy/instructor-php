<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Data\Collections;

use Cognesy\Addons\Chat\Contracts\CanProcessChatState;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Core\StateProcessors;

/**
 * @deprecated
 */
final readonly class ChatStateProcessors extends StateProcessors
{
    public function __construct(CanProcessChatState ...$processors) {
        parent::__construct(...$processors);
    }

//    public function withProcessors(CanProcessChatState ...$processors): static {
//        return new static(...$processors);
//    }

    protected function doProcess(object $processor, object $state, callable $next): object {
        assert($processor instanceof CanProcessChatState);
        assert($state instanceof ChatState);
        /** @var CanProcessChatState $processor */
        /** @var ChatState $state */
        return $processor->process($state, $next);
    }
}
