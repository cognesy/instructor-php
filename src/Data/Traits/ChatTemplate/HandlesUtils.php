<?php
namespace Cognesy\Instructor\Data\Traits\ChatTemplate;

use Cognesy\Instructor\Data\Messages\Message;
use Cognesy\Instructor\Data\Messages\Messages;
use Cognesy\Instructor\Data\Messages\Script;

trait HandlesUtils
{
    protected function normalizeMessages(string|array $messages): array {
        if (!is_array($messages)) {
            return [['role' => 'user', 'content' => $messages]];
        }
        return $messages;
    }

    protected function filterEmptySections(Script $script) : Script {
        foreach ($script->sections() as $section) {
            if ($this->isSectionEmpty($section->messages())) {
                $script->removeSection($section->name());
            }
        }
        return $script;
    }

    private function isSectionEmpty(Message|Messages $content) : bool {
        return match(true) {
            $content instanceof Messages => $content->isEmpty(),
            $content instanceof Message => $content->isEmpty() || $content->isNull(),
            default => true,
        };
    }
}