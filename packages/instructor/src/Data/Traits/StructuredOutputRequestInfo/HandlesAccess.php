<?php

namespace Cognesy\Instructor\Data\Traits\StructuredOutputRequestInfo;

use Cognesy\Instructor\Data\CachedContext;
use Cognesy\Instructor\Data\StructuredOutputConfig;

trait HandlesAccess
{
    public function isStreamed() : bool {
        return $this->options['stream'] ?? false;
    }

    public function messages() : array {
        return $this->messages;
    }

    public function responseModel(): string|array|object {
        return $this->responseModel;
    }

    public function model() : string {
        return $this->model;
    }

    public function system() : string {
        return $this->system;
    }

    public function prompt() : string {
        return $this->prompt;
    }

    public function options() : array {
        return $this->options;
    }

    public function option(string $name) : mixed {
        return $this->options[$name] ?? null;
    }

    public function examples() : array {
        return $this->examples;
    }

    public function cachedContext() : ?CachedContext {
        return $this->cachedContext;
    }

    public function config() : ?StructuredOutputConfig {
        return $this->config;
    }
}