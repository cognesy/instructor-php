<?php

namespace Cognesy\Polyglot\Inference\Events;

class StreamEventParsed extends InferenceEvent
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