<?php
namespace Cognesy\Instructor\Data\Messages\Traits\Section;

use Cognesy\Instructor\Data\Messages\Section;

trait HandlesTransformation
{
    public function toMergedPerRole() : static {
        return (new Section($this->name(), $this->description()))
            ->appendMessages(
                $this->messages()->toMergedPerRole()
            );
    }
}
