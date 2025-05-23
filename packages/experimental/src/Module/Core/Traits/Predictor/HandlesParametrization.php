<?php

namespace Cognesy\Experimental\Module\Core\Traits\Predictor;

use Cognesy\Experimental\Module\Signature\Signature;
use Cognesy\Instructor\Data\StructuredOutputRequestInfo;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\LLM\Inference;

trait HandlesParametrization
{
    public function using(
        Instructor                  $structuredOutput = null,
        StructuredOutputRequestInfo $requestInfo = null,
        Signature                   $signature = null,
        string                      $instructions = null,
        array                       $examples = null,
        array                       $options = null,
        string                      $model = null,
    ) : static {
        $this->withInstructor($structuredOutput);
        $this->withRequestInfo($requestInfo);
        $this->withSignature($signature);
        $this->withInstructions($instructions);
        $this->withExamples($examples);
        $this->withOptions($options);
        $this->withModel($model);
        return $this;
    }

    public function withInstructor(?StructuredOutput $structuredOutput) : static {
        $this->instructor = match(true) {
            !is_null($structuredOutput) => $structuredOutput,
            !isset($this->instructor) => new StructuredOutput(),
            default => $this->instructor,
        };
        return $this;
    }

    public function withInference(?Inference $inference) : static {
        $this->inference = match(true) {
            !is_null($inference) => $inference,
            !isset($this->inference) => new Inference(),
            default => $this->inference,
        };
        return $this;
    }

    public function withConnection(string $connection) : static {
        $this->connection = match(true) {
            !empty($connection) => $connection,
            !isset($this->connection) => '',
            default => $this->connection,
        };
        $this->inference->withConnection($this->connection);
        $this->instructor->withConnection($this->connection);
        return $this;
    }

    public function withRequestInfo(?StructuredOutputRequestInfo $requestInfo) : static {
        $this->requestInfo = match(true) {
            !is_null($requestInfo) => $requestInfo,
            !isset($this->requestInfo) => new StructuredOutputRequestInfo(),
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
