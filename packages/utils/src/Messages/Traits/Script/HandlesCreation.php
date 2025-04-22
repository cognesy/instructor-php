<?php

namespace Cognesy\Utils\Messages\Traits\Script;

use Cognesy\Utils\Messages\Messages;
use Cognesy\Utils\Messages\Script;
use Cognesy\Utils\Messages\Section;

trait HandlesCreation
{
    /**
     * @param array<string, string|array> $sections
     * @return static
     */
    static public function fromArray(array $sections) : Script {
        $sectionList = [];
        foreach ($sections as $name => $content) {
            $sectionList[] = (new Section($name))->appendMessages(
                match(true) {
                    is_string($content) => Messages::fromString($content),
                    is_array($content) => Messages::fromArray($content),
                }
            );
        }
        return new self(...$sectionList);
    }

    public static function fromMessages(Messages $messages, string $section = 'messages') : Script {
        return new self((new Section($section))->appendMessages($messages));
    }

    public function clone() : Script {
        return (new Script(...$this->sections))
            ->withParams($this->parameters());
    }
}