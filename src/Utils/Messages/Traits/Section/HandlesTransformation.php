<?php
namespace Cognesy\Instructor\Utils\Messages\Traits\Section;

use Cognesy\Instructor\Utils\Messages\Section;

trait HandlesTransformation
{
    public function toMergedPerRole() : static {
        return (new Section($this->name(), $this->description()))
            ->appendMessages(
                $this->messages()->toMergedPerRole()
            );
    }
}
