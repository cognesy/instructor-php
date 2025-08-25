<?php declare(strict_types=1);
namespace Cognesy\Template\Script\Traits\Script;

use Cognesy\Template\Script\Script;

trait HandlesTransformation
{
    /**
     * @param string|string[] $sections
     */
    public function select(string|array $sections = []) : static {
        $names = match (true) {
            empty($sections) => array_map(fn($section) => $section->name, $this->sections),
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
            sections: $selectedSections,
            parameters: $this->parameters,
        );
    }

    public function toMergedPerRole() : static {
        $mergedSections = [];
        foreach ($this->sections as $item) {
            if ($item->isEmpty()) {
                continue;
            }
            $mergedSections[] = $item->toMergedPerRole();
        }
        return new static(
            sections: $mergedSections,
            parameters: $this->parameters,
        );
    }

    public function trimmed() : static {
        $trimmedSections = [];
        foreach ($this->sections as $section) {
            $trimmed = $section->trimmed();
            if ($trimmed->isEmpty()) {
                continue;
            }
            $trimmedSections[] = $trimmed;
        }
        return new static(
            sections: $trimmedSections,
            parameters: $this->parameters,
        );
    }
}