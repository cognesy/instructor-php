<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Drivers\TypeSafe;

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Telemetry\HttpRequestTelemetry;
use Cognesy\Polyglot\Decision\Config\DecisionConfig;
use Cognesy\Polyglot\Decision\Contracts\DecisionRequestAdapter;
use Cognesy\Polyglot\Decision\Data\DecisionRequest;
use Cognesy\Polyglot\Decision\Drivers\SystemOne\SystemOneRequestEncoder;
use Override;

final readonly class TypesafeRequestAdapter implements DecisionRequestAdapter
{
    private SystemOneRequestEncoder $encoder;

    public function __construct(
        private DecisionConfig $config,
        ?SystemOneRequestEncoder $encoder = null,
    ) {
        $this->encoder = $encoder ?? new SystemOneRequestEncoder('TypeSafe');
    }

    #[Override]
    public function toHttpClientRequest(DecisionRequest $request): HttpRequest
    {
        $model = $request->model() ?? $this->config->model;
        $this->config->assertRoutingIdentity($model);
        $this->config->assertHttpTarget();
        $this->config->assertBearerAuthentication();

        $httpRequest = new HttpRequest(
            url: rtrim($this->config->apiUrl, '/').'/'.ltrim($this->config->endpoint, '/'),
            method: 'POST',
            headers: [
                'Authorization' => "Bearer {$this->config->apiKey}",
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            body: $this->encoder->encode($request, $model),
            options: [],
        );

        $correlation = $request->telemetryCorrelation();

        return match ($correlation) {
            null => $httpRequest,
            default => HttpRequestTelemetry::withCorrelation($httpRequest, $correlation),
        };
    }
}
