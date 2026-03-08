<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Minimaxi;

use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIResponseAdapter;
use RuntimeException;

class MinimaxiResponseAdapter extends OpenAIResponseAdapter
{
    #[\Override]
    protected function decodeResponseData(string $payload): array {
        $data = $this->decodeJsonData($payload, 'MiniMaxi response payload');
        $this->throwIfProviderReturnedError($data);

        if (!isset($data['choices']) || !is_array($data['choices'])) {
            throw new RuntimeException('Malformed MiniMaxi response payload: missing `choices` array.');
        }

        return $data;
    }

    /**
     * @param array<string,mixed> $data
     */
    private function throwIfProviderReturnedError(array $data): void {
        $baseResponse = $data['base_resp'] ?? null;
        if (!is_array($baseResponse)) {
            return;
        }

        $statusCode = (int) ($baseResponse['status_code'] ?? 0);
        $statusMessage = trim((string) ($baseResponse['status_msg'] ?? ''));

        if ($statusCode === 0 && $statusMessage === '') {
            return;
        }

        $suffix = match (true) {
            $statusMessage === '' => '',
            default => ': ' . $statusMessage,
        };

        throw new RuntimeException("MiniMaxi API error {$statusCode}{$suffix}");
    }
}
