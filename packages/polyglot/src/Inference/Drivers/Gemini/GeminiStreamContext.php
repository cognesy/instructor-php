<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Gemini;

use Cognesy\Polyglot\Inference\Assembly\ReplayAccumulator;
use Cognesy\Polyglot\Inference\Data\ReplayEnvelope;

/**
 * Derives stable semantic block identities from Gemini streams and aligns signatures.
 */
final class GeminiStreamContext
{
    /** @var array<string,string> */
    private array $activeType = [];
    /** @var array<string,string> */
    private array $activeKey = [];
    /** @var array<string,int> */
    private array $nextOrdinal = [];
    private ReplayAccumulator $replay;

    public function __construct()
    {
        $this->replay = new ReplayAccumulator(GeminiReplay::OWNER);
    }

    /** @param array<string,mixed> $part */
    public function blockKey(string $candidateId, array $part): string
    {
        $type = $this->semanticType($part);
        $explicitToolId = $part['functionCall']['id'] ?? null;
        if ($type === 'tool' && is_string($explicitToolId) && $explicitToolId !== '') {
            $key = 'gemini:' . $candidateId . ':tool:' . $explicitToolId;
            $this->rememberPart($key, $part);
            return $key;
        }

        $activeType = $this->activeType[$candidateId] ?? '';
        $key = $this->activeKey[$candidateId] ?? '';
        if ($key === '' || $activeType !== $type || $type === 'tool') {
            $ordinal = $this->nextOrdinal[$candidateId] ?? 0;
            $key = 'gemini:' . $candidateId . ':part:' . $ordinal;
            $this->nextOrdinal[$candidateId] = $ordinal + 1;
            $this->activeType[$candidateId] = $type;
            $this->activeKey[$candidateId] = $key;
        }
        $this->rememberPart($key, $part);
        return $key;
    }

    public function replay(): ?ReplayEnvelope
    {
        return $this->replay->envelope();
    }

    /** @param array<string,mixed> $part */
    private function rememberPart(string $key, array $part): void
    {
        $metadata = GeminiReplay::metadataForWirePart($part);
        $this->replay->remember($key, $metadata);
    }

    /** @param array<string,mixed> $part */
    private function semanticType(array $part): string
    {
        return match (true) {
            isset($part['functionCall']) => 'tool',
            ($part['thought'] ?? false) === true => 'reasoning',
            default => 'text',
        };
    }
}
