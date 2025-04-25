<?php

namespace Cognesy\Utils\Tests\Fixtures;

// Mock implementations for CanProvideMessage and CanProvideMessages interfaces
use Cognesy\Utils\Messages\Contracts\CanProvideMessage;
use Cognesy\Utils\Messages\Message;

class MockMessageProvider implements CanProvideMessage {
    public function toMessage(): Message {
        return new Message('user', 'From message provider');
    }
}
