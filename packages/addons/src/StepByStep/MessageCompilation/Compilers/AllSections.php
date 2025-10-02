<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\MessageCompilation\Compilers;

use Cognesy\Addons\StepByStep\MessageCompilation\CanCompileMessages;
use Cognesy\Addons\StepByStep\State\Contracts\HasMessageStore;
use Cognesy\Messages\Messages;

/**
 * @implements CanCompileMessages<HasMessageStore>
 */
final class AllSections implements CanCompileMessages
{
    #[\Override]
    public function compile(HasMessageStore $state): Messages
    {
        return $state->store()->toMessages();
    }
}
