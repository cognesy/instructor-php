<?php declare(strict_types=1);

namespace Cognesy\Messages\MessageStore\Traits\MessageStore;

use Cognesy\Messages\MessageStore\Collections\Sections;

trait HandlesTransformation
{
    /**
     * @param string|string[] $sections
     */
    public function select(string|array $sections = []) : static {
        $names = match (true) {
            empty($sections) => $this->sections->map(fn($section) => $section->name),
            is_string($sections) => [$sections],
            is_array($sections) => $sections,
        };
        $selectedSections = [];
        foreach ($names as $sectionName) {
            if ($this->hasSection($sectionName)) {
                $selectedSections[] = $this->section($sectionName);
            }
        }
        return new static(
            sections: new Sections(...$selectedSections),
            parameters: $this->parameters,
        );
    }

//    public function toMergedPerRole() : static {
//        $mergedSections = [];
//        foreach ($this->sections->each() as $item) {
//            if ($item->isEmpty()) {
//                continue;
//            }
//            $mergedSections[] = $item->toMergedPerRole();
//        }
//        return new static(
//            sections: new Sections(...$mergedSections),
//            parameters: $this->parameters,
//        );
//    }

    public function trimmed() : static {
        $trimmedSections = [];
        foreach ($this->sections->each() as $section) {
            $trimmed = $section->trimmed();
            if ($trimmed->isEmpty()) {
                continue;
            }
            $trimmedSections[] = $trimmed;
        }
        return new static(
            sections: new Sections(...$trimmedSections),
            parameters: $this->parameters,
        );
    }
}