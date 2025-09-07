<?php declare(strict_types=1);

namespace Cognesy\Messages\MessageStore\Traits\MessageStore;

use Cognesy\Messages\Messages;
use Cognesy\Messages\MessageStore\MessageStore;
use Cognesy\Messages\MessageStore\Section;
use Cognesy\Messages\MessageStore\Sections;

trait HandlesCreation
{
    /**
     * @param array<string, string|array> $sections
     * @return static
     */
    static public function fromArray(array $sections) : MessageStore {
        $sectionList = [];
        foreach ($sections as $name => $content) {
            $sectionList[] = (new Section($name))->appendMessages(
                match(true) {
                    is_string($content) => Messages::fromString($content),
                    is_array($content) => Messages::fromArray($content),
                }
            );
        }
        return new self($sectionList);
    }

    public static function fromMessages(Messages $messages, string $section = 'messages') : MessageStore {
        $sections = new Sections((new Section($section))->appendMessages($messages));
        return new self($sections);
    }

    public function clone() : self {
        return (new MessageStore($this->sections))
            ->withParams($this->parameters());
    }
}
