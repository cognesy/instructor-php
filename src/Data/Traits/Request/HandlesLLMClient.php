<?php

namespace Cognesy\Instructor\Data\Traits\Request;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\Http\Contracts\CanHandleHttp;
use Cognesy\Instructor\Extras\LLM\Contracts\CanHandleInference;
use Cognesy\Instructor\Extras\LLM\Data\LLMConfig;

trait HandlesLLMClient
{
    private string $connection;
    private LLMConfig $config;
    private CanHandleInference $driver;
    private CanHandleHttp $httpClient;
    private Mode $mode;
    private string $model;
    private array $options = [];

    public function connection() : string {
        return $this->connection;
    }

    public function mode() : Mode {
        return $this->mode;
    }

    public function model() : string {
        return $this->model;
    }

    public function setOption(string $key, mixed $value) : self {
        return $this->options[$key] = $value;
    }

    public function options() : array {
        return $this->options;
    }

    public function option(string $name) : mixed {
        return $this->options[$name] ?? null;
    }

    // INTERNAL //////////////////////////////////////////////////////////////////
}