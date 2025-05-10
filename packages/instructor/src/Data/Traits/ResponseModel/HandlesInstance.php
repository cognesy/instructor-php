<?php

namespace Cognesy\Instructor\Data\Traits\ResponseModel;

trait HandlesInstance
{
    private mixed $instance;

    public function instance() : mixed {
        return $this->instance;
    }

    public function withInstance(mixed $instance) : static {
        $this->instance = $instance;
        return $this;
    }
}