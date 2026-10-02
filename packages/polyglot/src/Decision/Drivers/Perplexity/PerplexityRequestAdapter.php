<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Drivers\Perplexity;

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Telemetry\HttpRequestTelemetry;
use Cognesy\Polyglot\Decision\Config\DecisionConfig;
use Cognesy\Polyglot\Decision\Contracts\DecisionRequestAdapter;
use Cognesy\Polyglot\Decision\Data\DecisionRequest;
use Cognesy\Polyglot\Decision\Drivers\SystemOne\SystemOneRequestEncoder;
use Cognesy\Polyglot\Decision\Questions\Noul;
use InvalidArgumentException;
use Override;

final readonly class PerplexityRequestAdapter implements DecisionRequestAdapter
{
    private SystemOneRequestEncoder $encoder;

    public function __construct(
        private DecisionConfig $config,
        ?SystemOneRequestEncoder $encoder = null,
    ) {
        $this->encoder = $encoder ?? new SystemOneRequestEncoder('Perplexity');
    }

    #[Override]
    public function toHttpClientRequest(DecisionRequest $request): HttpRequest
    {
        $model = $request->model() ?? $this->config->model;
        $this->config->assertRoutingIdentity($model);
        $this->config->assertHttpTarget();
        $this->config->assertBearerAuthentication();
        $this->assertNoulDefinitions($request);

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

    private function assertNoulDefinitions(DecisionRequest $request): void
    {
        foreach ($request->questions()->all() as $question) {
            if ($question instanceof Noul && $question->instructions() === null && $question->criteria() === null) {
                throw new InvalidArgumentException(
                    "Perplexity requires instructions or criteria for Noul question '{$question->id()}'.",
                );
            }
        }
    }
}
