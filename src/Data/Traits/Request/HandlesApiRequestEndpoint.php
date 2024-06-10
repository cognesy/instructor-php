<?php

namespace Cognesy\Instructor\Data\Traits\Request;

trait HandlesApiRequestEndpoint
{
    protected string $endpoint = '';

    public function endpoint() : string {
        return $this->endpoint;
    }
}