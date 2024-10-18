<?php

namespace Cognesy\Instructor\Extras\Evals\Data;

class Feedback
{
    /** @var ParameterFeedback[] $items */
    private array $items;

    public function __construct(
        string|array $items = []
    ) {
        $this->items = $this->toFeedbackItems($items);
    }

    public static function none() : static {
        return new static();
    }

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

    public function clear() : void {
        $this->items = [];
    }

    public function empty() : bool {
        return count($this->items) == 0;
    }

    public function notEmpty() : bool {
        return !$this->empty();
    }

    public function merge(Feedback $feedback) : void {
        $this->items = array_merge($this->items, $feedback->items());
    }

    public function __toString() : string {
        return implode(
            separator: "\n",
            array: array_map(
                callback: fn(ParameterFeedback $item) => $item->parameterName . ': ' . $item->feedback,
                array: $this->items
            ));
    }

    // INTERNAL /////////////////////////////////////////////////

    /**
     * @param array|string $items
     * @return ParameterFeedback[]
     */
    private function toFeedbackItems(array|string $items) : array {
        $feedbackItems = [];
        $items = is_array($items) ? $items : [$items];
        foreach ($items as $item) {
            if (is_string($item)) {
                $item = ['feedback' => $item];
            }
            $param = $item['parameterName'] ?? '';
            $feedback = $item['feedback'] ?? '';
            $feedbackItems[] = new ParameterFeedback($param, $feedback);
        }
        return $feedbackItems;
    }
}
