<?php
namespace Cognesy\Template\Script\Traits\Section;

use Cognesy\Template\Script\Section;

trait HandlesTransformation
{
    public function toMergedPerRole() : static {
        return (new Section($this->name(), $this->description()))
            ->appendMessages(
                $this->messages()->toMergedPerRole()
            );
    }
}
