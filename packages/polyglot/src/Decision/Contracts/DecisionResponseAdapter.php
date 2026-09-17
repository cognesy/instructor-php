<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Contracts;

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Polyglot\Decision\Data\DecisionRequest;
use Cognesy\Polyglot\Decision\Data\DecisionResponse;

interface DecisionResponseAdapter
{
    public function fromHttpResponse(HttpResponse $response, DecisionRequest $request): DecisionResponse;
}
