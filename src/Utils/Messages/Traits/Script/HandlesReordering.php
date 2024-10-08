<?php
namespace Cognesy\Instructor\Utils\Messages\Traits\Script;

use Cognesy\Instructor\Utils\Messages\Script;
use Cognesy\Instructor\Utils\Messages\Section;

trait HandlesReordering
{
    public function reorder(array $order) : Script {
        $sections = $this->listInOrder($order);

        $script = new Script();
        $script->parameters = $this->parameters();
        foreach ($sections as $section) {
            $script->appendSection($section);
        }
        return $script;
    }

    public function reverse() : Script {
        $script = new Script();
        $script->parameters = $this->parameters();
        foreach ($this->listReverse() as $section) {
            $script->appendSection($section);
        }
        return $script;
    }

    // INTERNAL ////////////////////////////////////////////////////

    /** @return Section[] */
    private function listAsIs() : array {
        return $this->sections;
    }

    /** @return Section[] */
    private function listReverse() : array {
        return array_reverse($this->sections);
    }

    /** @return Section[] */
    private function listInOrder(array $order) : array {
        $ordered = [];
        foreach ($order as $name) {
            if (!$this->hasSection($name)) {
                continue;
            }
            $section = $this->section($name);
            $ordered[] = $section;
        }
        return $ordered;
    }
}