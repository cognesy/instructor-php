<?php
namespace Cognesy\Utils\Messages\Traits\Section;

use Cognesy\Utils\Messages\Section;

trait HandlesTransformation
{
    public function toMergedPerRole() : static {
        return (new Section($this->name(), $this->description()))
            ->appendMessages(
                $this->messages()->toMergedPerRole()
            );
    }
}
