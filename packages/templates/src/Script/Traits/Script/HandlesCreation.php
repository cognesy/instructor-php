<?php

namespace Cognesy\Template\Script\Traits\Script;

use Cognesy\Template\Script\Script;
use Cognesy\Template\Script\Section;
use Cognesy\Utils\Messages\Messages;

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

    public function clone() : self {
        return (new Script(...$this->sections))
            ->withParams($this->parameters());
    }
}