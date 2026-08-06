<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\HttpClient;
use JsonException;
use Override;

final readonly class HttpAgentTarget implements CanRunAgentEvalTarget
{
    private EvalTracePolicy $policy;

    public function __construct(
        private HttpClient $client,
        private string $baseUrl,
        private ?string $authorization = null,
        private bool $healthCheck = true,
        ?EvalTracePolicy $policy = null,
    ) {
        $this->policy = $policy ?? EvalTracePolicy::safe();
    }

    #[Override]
    public function open(?EvalSessionRequest $request = null): CanUseAgentEvalSession {
        if ($this->healthCheck) {
            $this->health();
        }
        $payload = $this->request('POST', '/evals/sessions', [
            'caseId' => $request?->caseId,
            'description' => $request?->description,
        ]);
        $sessionId = $payload['sessionId'] ?? null;
        if (!is_string($sessionId) || $sessionId === '') {
            throw new HttpTargetException('Remote target did not return a sessionId.');
        }
        return new HttpEvalSession($this, $sessionId);
    }

    public function attach(string $sessionId): CanUseAgentEvalSession {
        $payload = $this->request('GET', '/evals/sessions/' . rawurlencode($sessionId));
        $run = self::runFromPayload($payload, $this->policy);
        return new HttpEvalSession($this, $sessionId, $run);
    }

    public function policy(): EvalTracePolicy {
        return $this->policy;
    }

    /** @return array<string, mixed> */
    public function sendTurn(string $sessionId, string $message): array {
        return $this->request(
            'POST',
            '/evals/sessions/' . rawurlencode($sessionId) . '/turns',
            ['message' => $message],
        );
    }

    /**
     * A remote agent server is a third party that knows nothing about
     * `EvalTracePolicy` and may send tool payloads verbatim - `$policy` (default
     * `safe()`) is applied to whatever it sends, exactly as it would be for a
     * local run, so the HTTP path is safe by default rather than degrading to
     * verbatim serialization just because the data already arrived pre-hydrated.
     *
     * @param array<string, mixed> $payload
     */
    public static function runFromPayload(array $payload, ?EvalTracePolicy $policy = null): AgentRun {
        $run = $payload['run'] ?? $payload;
        return is_array($run) ? AgentRun::fromArray($run, $policy) : throw new HttpTargetException('Remote target returned an invalid run snapshot.');
    }

    private function health(): void {
        $this->request('GET', '/health');
    }

    /** @param array<string, mixed> $body @return array<string, mixed> */
    private function request(string $method, string $path, array $body = []): array {
        $headers = ['Content-Type' => 'application/json', 'Accept' => 'application/json'];
        if ($this->authorization !== null) {
            $headers['Authorization'] = $this->authorization;
        }
        $response = $this->client->send(new HttpRequest(
            url: rtrim($this->baseUrl, '/') . $path,
            method: $method,
            headers: $headers,
            body: $body,
            options: [],
        ))->get();
        if ($response->statusCode() < 200 || $response->statusCode() >= 300) {
            throw new HttpTargetException("Remote target returned HTTP {$response->statusCode()}: " . substr($response->body(), 0, 512));
        }
        try {
            $data = json_decode($response->body(), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new HttpTargetException('Remote target returned malformed JSON.', previous: $error);
        }
        return is_array($data) ? $data : throw new HttpTargetException('Remote target JSON must be an object.');
    }
}
