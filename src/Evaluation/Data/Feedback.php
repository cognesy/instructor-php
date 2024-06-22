<?php

namespace Cognesy\Instructor\Evaluation\Data;

class Feedback
{
    public function __construct(
        /** @var VariableFeedback[] $items */
        public array $items = []
    ) {}

    /** @return VariableFeedback[] */
    public function items() : array {
        return $this->items;
    }

    public function add(?VariableFeedback $item) : static {
        if (is_null($item)) {
            return $this;
        }
        $this->items[] = $item;
        return $this;
    }
}
