<?php
namespace Cognesy\Instructor\Data\Traits\StructuredOutputRequest;

use Cognesy\Instructor\Data\CachedContext;
use Cognesy\Instructor\Data\StructuredOutputConfig;
use Cognesy\Polyglot\LLM\Enums\OutputMode;

trait HandlesAccess
{
    public function isStreamed() : bool {
        return $this->options['stream'] ?? false;
    }

    public function messages() : array {
        return $this->messages->toArray();
    }

    public function options() : array {
        return $this->options;
    }

    public function option(string $name) : mixed {
        return $this->options[$name] ?? null;
    }

    public function prompt() : string {
        return $this->prompt;
    }

    public function system() : string {
        return $this->system;
    }

    public function examples() : array {
        return $this->examples;
    }

    public function cachedContext() : CachedContext {
        return $this->cachedContext;
    }

    public function model() : string {
        return $this->model;
    }

    public function config() : StructuredOutputConfig {
        return $this->config;
    }


    public function mode() : OutputMode {
        return $this->config->outputMode();
    }

    public function retryPrompt() : string {
        return $this->config->retryPrompt();
    }
}