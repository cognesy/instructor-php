<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\MessageCompilation\Compilers;

use Cognesy\Addons\StepByStep\MessageCompilation\CanCompileMessages;
use Cognesy\Addons\StepByStep\State\Contracts\HasMessageStore;
use Cognesy\Messages\Messages;

/**
 * @implements CanCompileMessages<HasMessageStore>
 */
final class SelectedSections implements CanCompileMessages
{
    /**
     * @param string[] $sections
     */
    public function __construct(
        private readonly array $sections = [],
    ) {}

    #[\Override]
    public function compile(HasMessageStore $state): Messages
    {
        if ($this->sections === []) {
            return $state->messages();
        }

        // select() resolves in requested order, skips names with no section, and - unlike
        // the hand-rolled loop this replaces - collapses a repeated name instead of
        // emitting that section's messages twice.
        return $state->store()->sections()->select($this->sections)->toMessages();
    }
}
