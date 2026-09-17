<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Contracts;

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Polyglot\Decision\Data\DecisionRequest;

interface DecisionRequestAdapter
{
    public function toHttpClientRequest(DecisionRequest $request): HttpRequest;
}
