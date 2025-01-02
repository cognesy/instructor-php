<?php

namespace Cognesy\Instructor\Experimental\Module\Core;

class Feedback
{
    protected array $feedback = [];

    public function __construct(string|array $feedback = []) {
        $this->feedback = is_array($feedback) ? $feedback : [$feedback];
    }
    public function add(string $message) : void {
        $this->feedback[] = $message;
    }

    public function get() : array {
        return $this->feedback;
    }

    public function clear() : void {
        $this->feedback = [];
    }

    public function empty() : bool {
        return count($this->feedback) == 0;
    }

    public function nonEmpty() : bool {
        return !$this->empty();
    }

    public function merge(Feedback $feedback) : void {
        $this->feedback = array_merge($this->feedback, $feedback->get());
    }

    public function __toString() : string {
        return implode("\n", $this->feedback);
    }

}
