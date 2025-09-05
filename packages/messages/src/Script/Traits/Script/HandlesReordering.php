<?php declare(strict_types=1);
namespace Cognesy\Messages\Script\Traits\Script;

use Cognesy\Messages\Script\Script;

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

    /** @return \Cognesy\Messages\Script\Section[] */
    private function listAsIs() : array {
        return $this->sections;
    }

    /** @return \Cognesy\Messages\Script\Section[] */
    private function listReverse() : array {
        return array_reverse($this->sections);
    }

    /** @return \Cognesy\Messages\Script\Section[] */
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