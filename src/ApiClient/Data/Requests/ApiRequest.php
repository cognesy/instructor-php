<?php

namespace Cognesy\Instructor\ApiClient\Data\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class ApiRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        protected array $payload,
        protected string $endpoint,
    ) {
        $this->body()->setJsonFlags(JSON_UNESCAPED_SLASHES);
    }

    public function isStreamed(): bool {
        return $this->payload['stream'] ?? false;
    }

    public function resolveEndpoint() : string {
        return $this->endpoint;
    }

    protected function defaultBody(): array {
        return $this->payload;
    }
}