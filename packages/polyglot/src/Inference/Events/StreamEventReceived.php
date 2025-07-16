<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Events;

class StreamEventReceived extends InferenceEvent
{
    public function __construct(
        public string $content,
    ) {
        parent::__construct();
    }

    public function __toString(): string {
        return $this->content;
    }
}
