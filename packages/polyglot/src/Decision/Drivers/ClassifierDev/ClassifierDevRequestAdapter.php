<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Drivers\ClassifierDev;

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Telemetry\HttpRequestTelemetry;
use Cognesy\Polyglot\Decision\Config\DecisionConfig;
use Cognesy\Polyglot\Decision\Contracts\DecisionRequestAdapter;
use Cognesy\Polyglot\Decision\Data\DecisionRequest;
use Cognesy\Polyglot\Decision\Exceptions\DecisionInvalidRequestException;
use JsonException;
use Override;
use stdClass;

final readonly class ClassifierDevRequestAdapter implements DecisionRequestAdapter
{
    public function __construct(private DecisionConfig $config) {}

    #[Override]
    public function toHttpClientRequest(DecisionRequest $request): HttpRequest {
        $model = $request->model() ?? $this->config->model;
        $this->config->assertRoutingIdentity($model);
        $this->config->assertHttpTarget();
        if ($model !== 'fast') {
            throw new DecisionInvalidRequestException(
                "Classifier.dev supports only the 'fast' tier for typed Decision answers.",
            );
        }

        $map = ClassifierDevDimensionMap::fromRequest($request);
        $body = new stdClass();
        $body->items = [$map->item()];
        $body->tier = 'fast';
        $body->dimensions = $map->dimensions();

        try {
            $encoded = json_encode(
                $body,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
            );
        } catch (JsonException) {
            throw new DecisionInvalidRequestException('Classifier.dev request body cannot be encoded.');
        }

        $httpRequest = new HttpRequest(
            url: rtrim($this->config->apiUrl, '/') . '/' . ltrim($this->config->endpoint, '/'),
            method: 'POST',
            headers: [
                ...match (trim($this->config->apiKey)) {
                    '' => [],
                    default => ['Authorization' => "Bearer {$this->config->apiKey}"],
                },
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Idempotency-Key' => $request->id()->toString(),
            ],
            body: $encoded,
            options: [],
        );

        $correlation = $request->telemetryCorrelation();

        return match ($correlation) {
            null => $httpRequest,
            default => HttpRequestTelemetry::withCorrelation($httpRequest, $correlation),
        };
    }
}
