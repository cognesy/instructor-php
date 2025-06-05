<?php

namespace Cognesy\Polyglot\LLM\Traits;

use Cognesy\Polyglot\LLM\Data\LLMResponse;
use Cognesy\Polyglot\LLM\InferenceStream;

trait HandlesShortcuts
{
    public function stream(): InferenceStream {
        return $this->create()->stream();
    }

    public function response(): LLMResponse {
        return $this->create()->response();
    }

    // Shortcuts for creating responses in different formats

    public function get(): string {
        return $this->create()->get();
    }

    public function asJson(): string {
        return $this->create()->asJson();
    }

    public function asJsonData(): array {
        return $this->create()->asJsonData();
    }
}