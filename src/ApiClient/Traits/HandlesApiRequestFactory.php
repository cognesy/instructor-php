<?php
namespace Cognesy\Instructor\ApiClient\Traits;

use Cognesy\Instructor\ApiClient\Factories\ApiRequestFactory;

trait HandlesApiRequestFactory
{
    protected ApiRequestFactory $apiRequestFactory;

    public function withApiRequestFactory(ApiRequestFactory $apiRequestFactory): static {
        $this->apiRequestFactory = $apiRequestFactory;
        return $this;
    }
}