<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Drivers\Clef;

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Telemetry\HttpRequestTelemetry;
use Cognesy\Polyglot\Decision\Config\DecisionConfig;
use Cognesy\Polyglot\Decision\Contracts\DecisionRequestAdapter;
use Cognesy\Polyglot\Decision\Data\DecisionRequest;
use Cognesy\Polyglot\Decision\Drivers\SystemOne\SystemOneRequestEncoder;
use Cognesy\Polyglot\Decision\Questions\Choice;
use Cognesy\Polyglot\Decision\Questions\Noul;
use Cognesy\Polyglot\Decision\Questions\Score;
use InvalidArgumentException;
use Override;

/**
 * Cloudflare Workers AI routes Clef by model in the URL path, so the endpoint
 * may contain a `{model}` placeholder (e.g. `/run/@cf/cloudflare/{model}`).
 */
final readonly class ClefRequestAdapter implements DecisionRequestAdapter
{
    private SystemOneRequestEncoder $encoder;

    public function __construct(
        private DecisionConfig $config,
        ?SystemOneRequestEncoder $encoder = null,
    ) {
        $this->encoder = $encoder ?? new SystemOneRequestEncoder('Clef');
    }

    #[Override]
    public function toHttpClientRequest(DecisionRequest $request): HttpRequest
    {
        $model = $request->model() ?? $this->config->model;
        $this->config->assertRoutingIdentity($model);
        $this->config->assertHttpTarget();
        $this->config->assertBearerAuthentication();
        $this->assertInstructions($request);

        $httpRequest = new HttpRequest(
            url: rtrim($this->config->apiUrl, '/').str_replace('{model}', rawurlencode($model), $this->config->endpoint),
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

    private function assertInstructions(DecisionRequest $request): void
    {
        foreach ($request->questions()->all() as $question) {
            /** @var Noul|Choice|Score $question */
            if ($question->instructions() === null) {
                throw new InvalidArgumentException("Clef requires instructions for question '{$question->id()}'.");
            }
        }
    }
}
