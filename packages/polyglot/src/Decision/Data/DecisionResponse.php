<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Data;

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Polyglot\Decision\Collections\Answers;
use Cognesy\Polyglot\Decision\Internal\DecisionData;

final readonly class DecisionResponse
{
    private string $model;

    public function __construct(
        private Answers $answers,
        string $model,
        private DecisionUsage $usage = new DecisionUsage,
        private HttpResponse $responseData = new HttpResponse(0, '', [], false),
        private DecisionProviderRequestId $providerRequestId = new DecisionProviderRequestId,
    ) {
        $this->model = DecisionData::nonEmptyString($model, 'Decision response model');
    }

    public function answers(): Answers
    {
        return $this->answers;
    }

    public function model(): string
    {
        return $this->model;
    }

    public function usage(): DecisionUsage
    {
        return $this->usage;
    }

    public function responseData(): HttpResponse
    {
        return $this->responseData;
    }

    public function providerRequestId(): DecisionProviderRequestId
    {
        return $this->providerRequestId;
    }
}
