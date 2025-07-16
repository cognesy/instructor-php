<?php declare(strict_types=1);
namespace Cognesy\Instructor\Data\Traits\StructuredOutputRequest;

use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Data\CachedContext;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Messages\Messages;

trait HandlesAccess
{
    public function messages() : Messages {
        return $this->messages;
    }

    public function options() : array {
        return $this->options;
    }

    public function option(string $name) : mixed {
        return $this->options[$name] ?? null;
    }

    public function isStreamed() : bool {
        return $this->options['stream'] ?? false;
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
}