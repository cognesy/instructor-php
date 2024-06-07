<?php

namespace Cognesy\Instructor\Data\Traits\Request;

trait HandlesData
{
    protected array $data = [];

    public function data() : array {
        return $this->data;
    }
}