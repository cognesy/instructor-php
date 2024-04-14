<?php

namespace Cognesy\Instructor\ApiClient\Traits;

trait HandlesQueryParams
{
    protected array $queryParams = [];

    public function withQueryParam(string $name, string $value): self {
        $this->queryParams[$name] = $value;
        return $this;
    }
}