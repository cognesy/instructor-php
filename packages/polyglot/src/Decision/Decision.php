<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision;

use Cognesy\Polyglot\Decision\Collections\Answers;
use Cognesy\Polyglot\Decision\Collections\Questions;
use Cognesy\Polyglot\Decision\Config\DecisionConfig;
use Cognesy\Polyglot\Decision\Config\DecisionRetryPolicy;
use Cognesy\Polyglot\Decision\Contracts\CanCreateDecision;
use Cognesy\Polyglot\Decision\Contracts\CanProvideDecisionDrivers;
use Cognesy\Polyglot\Decision\Data\DecisionRequest;
use Cognesy\Polyglot\Decision\Data\DecisionResponse;
use Cognesy\Polyglot\Decision\Data\JsonContent;
use InvalidArgumentException;
use Override;

final class Decision implements CanCreateDecision
{
    private CanCreateDecision $runtime;

    private ?DecisionRequest $request = null;

    private ?JsonContent $input = null;

    private Questions $questions;

    private ?string $model = null;

    private ?DecisionRetryPolicy $retryPolicy = null;

    public function __construct(?CanCreateDecision $runtime = null)
    {
        $this->runtime = $runtime ?? DecisionRuntime::fromProvider(DecisionProvider::new());
        $this->questions = Questions::empty();
    }

    public static function fromConfig(
        DecisionConfig $config,
        ?CanProvideDecisionDrivers $drivers = null,
    ): self {
        return new self(DecisionRuntime::fromConfig(config: $config, drivers: $drivers));
    }

    public static function fromProvider(
        DecisionProvider $provider,
        ?CanProvideDecisionDrivers $drivers = null,
    ): self {
        return new self(DecisionRuntime::fromProvider(provider: $provider, drivers: $drivers));
    }

    public static function fromRuntime(CanCreateDecision $runtime): self
    {
        return new self($runtime);
    }

    public static function using(
        string $preset,
        ?string $basePath = null,
        ?CanProvideDecisionDrivers $drivers = null,
    ): self {
        return self::fromConfig(DecisionConfig::fromPreset($preset, $basePath), $drivers);
    }

    public function withRuntime(CanCreateDecision $runtime): self
    {
        $copy = clone $this;
        $copy->runtime = $runtime;

        return $copy;
    }

    public function withInput(string|JsonContent $input): self
    {
        $copy = clone $this;
        if ($copy->request !== null) {
            $copy->request = $copy->request->withInput($input);
        } else {
            $copy->input = is_string($input) ? JsonContent::text($input) : $input;
        }

        return $copy;
    }

    public function withQuestions(Questions $questions): self
    {
        $copy = clone $this;
        if ($copy->request !== null) {
            $copy->request = $copy->request->withQuestions($questions);
        } else {
            $copy->questions = $questions;
        }

        return $copy;
    }

    public function withModel(string $model): self
    {
        if (trim($model) === '') {
            throw new InvalidArgumentException('Decision model must be a non-empty string.');
        }
        $copy = clone $this;
        if ($copy->request !== null) {
            $copy->request = $copy->request->withModel($model);
        } else {
            $copy->model = $model;
        }

        return $copy;
    }

    public function withRetryPolicy(DecisionRetryPolicy $retryPolicy): self
    {
        $copy = clone $this;
        if ($copy->request !== null) {
            $copy->request = $copy->request->withRetryPolicy($retryPolicy);
        } else {
            $copy->retryPolicy = $retryPolicy;
        }

        return $copy;
    }

    public function withRequest(DecisionRequest $request): self
    {
        $copy = clone $this;
        $copy->request = $request;

        return $copy;
    }

    public function with(
        string|JsonContent|null $input = null,
        ?Questions $questions = null,
        ?string $model = null,
        ?DecisionRetryPolicy $retryPolicy = null,
    ): self {
        $copy = $this;
        if ($input !== null) {
            $copy = $copy->withInput($input);
        }
        if ($questions !== null) {
            $copy = $copy->withQuestions($questions);
        }
        if ($model !== null) {
            $copy = $copy->withModel($model);
        }
        if ($retryPolicy !== null) {
            $copy = $copy->withRetryPolicy($retryPolicy);
        }

        return $copy;
    }

    public function get(): Answers
    {
        return $this->create()->get();
    }

    public function response(): DecisionResponse
    {
        return $this->create()->response();
    }

    #[Override]
    public function create(?DecisionRequest $request = null): PendingDecision
    {
        return $this->runtime->create($request ?? $this->request ?? $this->buildRequest());
    }

    private function buildRequest(): DecisionRequest
    {
        if ($this->input === null) {
            throw new InvalidArgumentException('Decision input is required.');
        }

        return new DecisionRequest(
            input: $this->input,
            questions: $this->questions,
            model: $this->model,
            retryPolicy: $this->retryPolicy,
        );
    }
}
