<?php

namespace Cognesy\Instructor\Extras\Module\Core\Traits\Predictor;

use Cognesy\Instructor\ApiClient\Contracts\CanCallApi;
use Cognesy\Instructor\Data\RequestInfo;
use Cognesy\Instructor\Extras\Module\Signature\Signature;
use Cognesy\Instructor\Instructor;

trait HandlesParametrization
{
    public function using(
        Instructor $instructor = null,
        CanCallApi $client = null,
        RequestInfo $requestInfo = null,
        Signature $signature = null,
        string $instructions = null,
        array $examples = null,
        array $options = null,
        string $model = null,
    ) : static {
        $this->withInstructor($instructor);
        $this->withClient($client);
        $this->withRequestInfo($requestInfo);
        $this->withSignature($signature);
        $this->withInstructions($instructions);
        $this->withExamples($examples);
        $this->withOptions($options);
        $this->withModel($model);
        return $this;
    }

    public function withInstructor(?Instructor $instructor) : static {
        $this->instructor = match(true) {
            !is_null($instructor) => $instructor,
            !isset($this->instructor) => new Instructor(),
            default => $this->instructor,
        };
        return $this;
    }

    public function withClient(?CanCallApi $client) : static {
        $this->instructor->withClient(match(true) {
            !is_null($client) => $client,
            default => $this->instructor->client(),
        });
        return $this;
    }

    public function withRequestInfo(?RequestInfo $requestInfo) : static {
        $this->requestInfo = match(true) {
            !is_null($requestInfo) => $requestInfo,
            !isset($this->requestInfo) => new RequestInfo(),
            default => $this->requestInfo,
        };
        return $this;
    }

    public function withSignature(?Signature $signature) : static {
        $this->signature = match(true) {
            !is_null($signature) => $signature,
            !isset($this->signature) => null,
            default => $this->signature,
        };
        return $this;
    }

    public function withInstructions(?string $instructions) : static {
        $this->instructions = match(true) {
            !is_null($instructions) => $instructions,
            !isset($this->instructions) => $this->signature->getDescription(),
            default => $this->instructions,
        };
        return $this;
    }

    public function withExamples(?array $examples) : static {
        $this->requestInfo->examples = match(true) {
            !is_null($examples) => $examples,
            !isset($this->requestInfo->examples) => [],
            default => $this->requestInfo->examples,
        };
        return $this;
    }

    public function withOptions(?array $options) : static {
        $this->requestInfo->options = match(true) {
            !is_null($options) => $options,
            !isset($this->requestInfo->options) => [],
            default => $this->requestInfo->options,
        };
        return $this;
    }

    public function withModel(?string $model) : static {
        $this->requestInfo->model = match(true) {
            !is_null($model) => $model,
            !isset($this->requestInfo->model) => '',
            default => $this->requestInfo->model,
        };
        return $this;
    }

    public function withRoleDescription(?string $roleDescription) : static {
        $this->roleDescription = match(true) {
            !is_null($roleDescription) => $roleDescription,
            !isset($this->roleDescription) => '',
            default => $this->roleDescription,
        };
        return $this;
    }
}
