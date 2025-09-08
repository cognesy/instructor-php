<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Compilers;

use Cognesy\Addons\Chat\Contracts\CanCompileMessages;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Messages\Messages;
use Cognesy\Messages\MessageStore\Sections;

class SelectedSections implements CanCompileMessages {
    public function __construct(
        public array $sections = [],
    ) {}

    public function compile(ChatState $state): Messages {
        if (empty($this->sections)) {
            return $state->messages();
        }
        $sectionList = [];
        foreach ($this->sections as $sectionName) {
            $sectionList[] = $state->store()->sections()->get($sectionName);
        }
        $sections = new Sections(...$sectionList);
        return $sections->toMessages();
    }
}