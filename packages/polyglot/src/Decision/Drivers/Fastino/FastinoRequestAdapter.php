<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Drivers\Fastino;

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Telemetry\HttpRequestTelemetry;
use Cognesy\Polyglot\Decision\Config\DecisionConfig;
use Cognesy\Polyglot\Decision\Contracts\DecisionRequestAdapter;
use Cognesy\Polyglot\Decision\Data\DecisionRequest;
use Cognesy\Polyglot\Decision\Drivers\SystemOne\SystemOneRequestEncoder;
use Cognesy\Polyglot\Decision\Exceptions\DecisionInvalidRequestException;
use Cognesy\Polyglot\Decision\Questions\Choice;
use Cognesy\Polyglot\Decision\Questions\Noul;
use Cognesy\Polyglot\Decision\Questions\Score;
use Override;

final readonly class FastinoRequestAdapter implements DecisionRequestAdapter
{
    public const int MAX_SCORE_LEVELS = 255;

    private SystemOneRequestEncoder $encoder;

    public function __construct(
        private DecisionConfig $config,
        ?SystemOneRequestEncoder $encoder = null,
    ) {
        $this->encoder = $encoder ?? new SystemOneRequestEncoder('Fastino', self::MAX_SCORE_LEVELS);
    }

    #[Override]
    public function toHttpClientRequest(DecisionRequest $request): HttpRequest
    {
        $model = $request->model() ?? $this->config->model;
        $this->config->assertRoutingIdentity($model);
        $this->config->assertHttpTarget();
        $this->config->assertBearerAuthentication();
        foreach ($request->questions()->all() as $question) {
            $this->assertQuestion($question);
        }

        $httpRequest = new HttpRequest(
            url: rtrim($this->config->apiUrl, '/').'/'.ltrim($this->config->endpoint, '/'),
            method: 'POST',
            headers: [
                'X-API-Key' => $this->config->apiKey,
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

    private function assertQuestion(Noul|Choice|Score $question): void
    {
        $instructions = $question->instructions()?->value();
        if (! is_string($instructions) || trim($instructions) === '') {
            throw new DecisionInvalidRequestException(
                "Fastino requires text instructions for question '{$question->id()}'.",
            );
        }
        if (! $question instanceof Score) {
            return;
        }
        foreach ($question->levels()->toArray() as $level) {
            if (! is_string($level)) {
                throw new DecisionInvalidRequestException(
                    "Fastino Score levels must be text for question '{$question->id()}'.",
                );
            }
        }
    }
}
