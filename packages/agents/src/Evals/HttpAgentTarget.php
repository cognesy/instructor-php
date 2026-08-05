<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\HttpClient;
use JsonException;
use Override;

final readonly class HttpAgentTarget implements CanRunAgentEvalTarget
{
    public function __construct(
        private HttpClient $client,
        private string $baseUrl,
        private ?string $authorization = null,
        private bool $healthCheck = true,
    ) {}

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
        $run = self::runFromPayload($payload);
        return new HttpEvalSession($this, $sessionId, $run);
    }

    /** @return array<string, mixed> */
    public function sendTurn(string $sessionId, string $message): array {
        return $this->request(
            'POST',
            '/evals/sessions/' . rawurlencode($sessionId) . '/turns',
            ['message' => $message],
        );
    }

    /** @param array<string, mixed> $payload */
    public static function runFromPayload(array $payload): AgentRun {
        $run = $payload['run'] ?? $payload;
        return is_array($run) ? AgentRun::fromArray($run) : throw new HttpTargetException('Remote target returned an invalid run snapshot.');
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
