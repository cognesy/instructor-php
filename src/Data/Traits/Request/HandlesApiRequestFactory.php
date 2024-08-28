<?php
namespace Cognesy\Instructor\Data\Traits\Request;

use Cognesy\Instructor\ApiClient\Factories\ApiRequestFactory;
use Cognesy\Instructor\ApiClient\Requests\ApiRequest;

trait HandlesApiRequestFactory
{
    private ?ApiRequestFactory $apiRequestFactory;

    public function apiRequestFactory() : ApiRequestFactory {
        return $this->apiRequestFactory;
    }

    public function toApiRequest() : ApiRequest {
        $requestClass = $this->client->getModeRequestClass($this->mode());
        return $this->apiRequestFactory->makeRequest(
            requestClass: $requestClass,
            body: array_filter(array_merge(
                ['messages' => $this->toMessages()],
                ['model' => $this->model()],
                $this->options(),
            )),
            endpoint: $this->endpoint(),
            method: $this->method(),
            data: $this->data(),
        );
    }

    // INTERNAL ///////////////////////////////////////////////////////////////

    protected function withApiRequestFactory(ApiRequestFactory $apiRequestFactory): static {
        $this->apiRequestFactory = $apiRequestFactory;
        return $this;
    }
}