<?php

namespace Cognesy\Instructor\Data\Traits\Request;

trait HandlesEndpoint
{
    protected string $endpoint = '';

    public function endpoint() : string {
        return $this->endpoint;
    }
}