<?php
namespace Cognesy\Instructor\Data\Traits\Request;

trait HandlesApiRequestOptions
{
    private array $options = [];

    public function isStream() : bool {
        return $this->option('stream', false);
    }

    public function options() : array {
        return $this->options;
    }

    public function option(string $key, mixed $defaultValue = null) : mixed {
        return $this->options[$key] ?? $defaultValue;
    }

    // INTERNAL ///////////////////////////////////////////////////////////////

    protected function setOption(string $name, mixed $value) : self {
        $this->options[$name] = $value;
        return $this;
    }

    protected function unsetOption(string $name) : self {
        unset($this->options[$name]);
        return $this;
    }
}