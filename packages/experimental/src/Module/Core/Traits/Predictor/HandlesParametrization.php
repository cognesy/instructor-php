<?php

namespace Cognesy\Experimental\Module\Core\Traits\Predictor;

use Cognesy\Experimental\Module\Signature\Signature;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\Inference\Inference;

trait HandlesParametrization
{
    public function with(
        StructuredOutput        $structuredOutput = null,
        StructuredOutputRequest $request = null,
        Signature               $signature = null,
        string                  $instructions = null,
        array                   $examples = null,
        array                   $options = null,
        string                  $model = null,
    ) : static {
        $this->withStructuredOutput($structuredOutput);
        $this->withRequest($request);
        $this->withSignature($signature);
        $this->withInstructions($instructions);
        $this->withExamples($examples);
        $this->withOptions($options);
        $this->withModel($model);
        return $this;
    }

    public function withStructuredOutput(?StructuredOutput $structuredOutput) : static {
        $this->structuredOutput = match(true) {
            !is_null($structuredOutput) => $structuredOutput,
            !isset($this->structuredOutput) => new StructuredOutput(),
            default => $this->structuredOutput,
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

    public function using(string $preset) : static {
        $this->preset = match(true) {
            !empty($preset) => $preset,
            !isset($this->preset) => '',
            default => $this->preset,
        };
        $this->inference->using($this->preset);
        $this->structuredOutput->using($this->preset);
        return $this;
    }

    public function withRequest(?StructuredOutputRequest $request) : static {
        $this->requestInfo = match(true) {
            !is_null($request) => $request,
            !isset($this->requestInfo) => new StructuredOutputRequest(),
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
        $this->requestInfo->withExamples($examples ?? $this->requestInfo->examples());
        return $this;
    }

    public function withOptions(?array $options) : static {
        $this->requestInfo->withOptions($options ?? $this->requestInfo->options());
        return $this;
    }

    public function withModel(?string $model) : static {
        $this->requestInfo->withModel($model ?? $this->requestInfo->model());
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
