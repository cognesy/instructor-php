<?php declare(strict_types=1);
namespace Cognesy\Messages\MessageStore\Traits\Section;

use Cognesy\Messages\MessageStore\Section;

trait HandlesTransformation
{
    public function toMergedPerRole() : static {
        return (new Section($this->name(), $this->description()))
            ->appendMessages(
                $this->messages()->toMergedPerRole()
            );
    }

    public function trimmed() : static {
        $section = new Section($this->name(), $this->description());
        $section = $section->withMessages($this->messages()->trimmed());
        return $section;
    }
}
