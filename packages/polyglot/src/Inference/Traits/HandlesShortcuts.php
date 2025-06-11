<?php

namespace Cognesy\Polyglot\Inference\Traits;

use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\InferenceStream;

trait HandlesShortcuts
{
    public function stream(): InferenceStream {
        return $this->create()->stream();
    }

    public function response(): InferenceResponse {
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