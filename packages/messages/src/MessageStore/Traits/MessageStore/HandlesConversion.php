<?php declare(strict_types=1);

namespace Cognesy\Messages\MessageStore\Traits\MessageStore;

use Cognesy\Messages\Messages;

trait HandlesConversion
{
    public function toMessages() : Messages {
        return $this->sections->toMessages();
    }

    /**
     * @param array<string> $order
     * @return array<string,string|array>
     */
    public function toArray() : array {
        return $this->toMessages()->toArray();
    }

    /**
     * @param array<string> $order
     */
    public function toString() : string {
        return $this->toMessages()->toString();
    }
}