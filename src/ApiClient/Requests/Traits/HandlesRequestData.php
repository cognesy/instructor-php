<?php

namespace Cognesy\Instructor\ApiClient\Requests\Traits;

trait HandlesRequestData
{
    protected function getData(string $name, mixed $defaultValue) : mixed {
        return $this->data[$name] ?? $defaultValue;
    }

    private function pullBodyField(string $name, mixed $default = null): mixed {
        $value = $this->requestBody[$name] ?? $default;
        unset($this->requestBody[$name]);
        return $value;
    }

    public function isStreamed(): bool {
        return $this->requestBody['stream'] ?? false;
    }

    protected function model() : string {
        return $this->model;
    }
}