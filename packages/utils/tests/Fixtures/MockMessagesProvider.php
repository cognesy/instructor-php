<?php

namespace Cognesy\Utils\Tests\Fixtures;

use Cognesy\Utils\Messages\Contracts\CanProvideMessages;
use Cognesy\Utils\Messages\Messages;

class MockMessagesProvider implements CanProvideMessages {
    public function toMessages(): Messages {
        return Messages::fromString('From messages provider');
    }
}
