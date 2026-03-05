<?php declare(strict_types=1);

namespace Middleware\AuthenticationMiddleware;

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Middleware\Base\BaseMiddleware;

final class AuthenticationMiddleware extends BaseMiddleware
{
    public function __construct(
        private string $apiKey,
    ) {}

    protected function beforeRequest(HttpRequest $request): HttpRequest
    {
        return $request->withHeader('Authorization', 'Bearer ' . $this->apiKey);
    }
}
