<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Context\Compilers;

use Cognesy\Agents\Core\Context\CanCompileMessages;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Messages\Messages;
use Cognesy\Messages\MessageStore\Collections\Sections;

final class SelectedSections implements CanCompileMessages
{
    /**
     * @param string[] $sections
     */
    public function __construct(
        private readonly array $sections = [],
    ) {}

    #[\Override]
    public function compile(AgentState $state): Messages
    {
        if ($this->sections === []) {
            return $state->messages();
        }

        $store = $state->store();
        $resolved = [];
        foreach ($this->sections as $sectionName) {
            $section = $store->sections()->get($sectionName);
            if ($section === null) {
                continue;
            }
            $resolved[] = $section;
        }

        if ($resolved === []) {
            return Messages::empty();
        }

        return (new Sections(...$resolved))->toMessages();
    }
}
