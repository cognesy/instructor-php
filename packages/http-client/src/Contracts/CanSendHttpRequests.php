<?php declare(strict_types=1);

namespace Cognesy\Http\Contracts;

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\PendingHttpResponse;

interface CanSendHttpRequests
{
    public function send(HttpRequest $request): PendingHttpResponse;
}
