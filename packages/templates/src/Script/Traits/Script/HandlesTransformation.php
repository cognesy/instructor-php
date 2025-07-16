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
        $script = new Script();
        $script->withParams($this->parameters);
        foreach ($names as $sectionName) {
            $script->appendSection($this->section($sectionName));
        }
        return $script;
    }

    public function toMergedPerRole() : static {
        $script = new Script();
        $script->withParams($this->parameters());
        foreach ($this->sections as $item) {
            if ($item->isEmpty()) {
                continue;
            }
            $script->appendSection($item->toMergedPerRole());
        }
        return $script;
    }

    public function trimmed() : static {
        $script = new Script();
        $script->withParams($this->parameters());
        foreach ($this->sections as $section) {
            $trimmed = $section->trimmed();
            if ($trimmed->isEmpty()) {
                continue;
            }
            $script->appendSection($trimmed);
        }
        return $script;
    }
}