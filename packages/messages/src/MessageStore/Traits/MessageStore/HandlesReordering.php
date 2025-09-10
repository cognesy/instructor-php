<?php declare(strict_types=1);

namespace Cognesy\Messages\MessageStore\Traits\MessageStore;

use Cognesy\Messages\MessageStore\MessageStore;
use Cognesy\Messages\MessageStore\Section;

/** @deprecated */
trait HandlesReordering
{
    public function reorder(array $order) : MessageStore {
        $sections = $this->listInOrder($order);

        $store = new MessageStore();
        $store->parameters = $this->parameters();
        foreach ($sections as $section) {
            $store->appendSection($section);
        }
        return $store;
    }

    public function reverse() : MessageStore {
        $store = new MessageStore();
        $store->parameters = $this->parameters();
        foreach ($this->listReverse() as $section) {
            $store->appendSection($section);
        }
        return $store;
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