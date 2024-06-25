<?php

namespace Cognesy\Instructor\Evaluation\Data;

class Feedback
{
    public function __construct(
        /** @var ParameterFeedback[] $items */
        public array $items = []
    ) {}

    /** @return ParameterFeedback[] */
    public function items() : array {
        return $this->items;
    }

    public function add(?ParameterFeedback $item) : static {
        if (is_null($item)) {
            return $this;
        }
        $this->items[] = $item;
        return $this;
    }
}
