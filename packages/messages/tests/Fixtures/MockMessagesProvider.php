<?php

namespace Cognesy\Messages\Tests\Fixtures;

use Cognesy\Messages\Contracts\CanProvideMessages;
use Cognesy\Messages\Messages;

class MockMessagesProvider implements CanProvideMessages {
    public function toMessages(): Messages {
        return Messages::fromString('From messages provider');
    }
}
