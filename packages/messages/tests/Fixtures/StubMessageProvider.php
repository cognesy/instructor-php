<?php

namespace Cognesy\Messages\Tests\Fixtures;

// Mock implementations for CanProvideMessage and CanProvideMessages interfaces
use Cognesy\Messages\Contracts\CanProvideMessage;
use Cognesy\Messages\Message;

class StubMessageProvider implements CanProvideMessage {
    public function toMessage(): Message {
        return new Message('user', 'From message provider');
    }
}
