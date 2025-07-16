<?php declare(strict_types=1);
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

    public function trimmed() : static {
        $section = new Section($this->name(), $this->description());
        $section->withMessages($this->messages()->trimmed());
        return $section;
    }
}
