<?php declare(strict_types=1);

namespace Cognesy\Addons\Core\MessageCompilation;

use Cognesy\Addons\Core\State\Contracts\HasMessageStore;
use Cognesy\Messages\Messages;

final class AllSections implements CanCompileMessages
{
    public function compile(HasMessageStore $state): Messages
    {
        return $state->store()->toMessages();
    }
}
