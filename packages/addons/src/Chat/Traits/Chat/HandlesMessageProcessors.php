<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Traits\Chat;

use Cognesy\Addons\Chat\Contracts\CanProcessMessage;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Messages\Messages;

trait HandlesMessageProcessors
{
    /** @var CanProcessMessage[] */
    protected array $messageProcessors = [];

    public function withMessageProcessors(CanProcessMessage ...$processors) : self {
        foreach ($processors as $p) { $this->messageProcessors[] = $p; }
        return $this;
    }

    protected function applyBeforeSend(Messages $messages, ChatState $state) : Messages {
        $result = $messages;
        foreach ($this->messageProcessors as $p) { $result = $p->beforeSend($result, $state); }
        return $result;
    }

    protected function applyBeforeAppend(Messages $messages, ChatState $state) : Messages {
        $result = $messages;
        foreach ($this->messageProcessors as $p) { $result = $p->beforeAppend($result, $state); }
        return $result;
    }
}

