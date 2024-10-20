<?php

namespace Cognesy\Instructor\Extras\Evals\Data;

class Feedback
{
    /** @var FeedbackItem[] $items */
    private array $items;

    /**
     * @param string|FeedbackItem[] $items
     */
    public function __construct(
        string|array $items = []
    ) {
        $this->items = $this->toFeedbackItems($items);
    }

    public static function none() : static {
        return new static();
    }

    /** @return FeedbackItem[] */
    public function items() : array {
        return $this->items;
    }

    public function add(?FeedbackItem $item) : static {
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
                callback: fn(FeedbackItem $item) => $item->context . ': ' . $item->feedback,
                array: $this->items
            ));
    }

    // INTERNAL /////////////////////////////////////////////////

    /**
     * @param array|string $items
     * @return FeedbackItem[]
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
            $feedbackItems[] = new FeedbackItem($param, $feedback);
        }
        return $feedbackItems;
    }
}
