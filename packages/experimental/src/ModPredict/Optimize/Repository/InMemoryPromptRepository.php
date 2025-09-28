<?php declare(strict_types=1);

namespace Cognesy\Experimental\ModPredict\Optimize\Repository;

use Cognesy\Experimental\ModPredict\Optimize\Contracts\PromptRepository;
use Cognesy\Experimental\ModPredict\Optimize\Data\PromptPreset;

final class InMemoryPromptRepository implements PromptRepository
{
    /** @var array<string, PromptPreset> key: signatureId|modelId|version */
    private array $store = [];
    /** @var array<string, string> key: signatureId|modelId => version */
    private array $active = [];
    /** @var array<string, list<string>> key: signatureId|modelId => [versions] */
    private array $canaries = [];

    public function getActive(string $signatureId, string $modelId): ?PromptPreset {
        $key = $this->pairKey($signatureId, $modelId);
        $v = $this->active[$key] ?? null;
        return $v ? ($this->store[$this->tripleKey($signatureId, $modelId, $v)] ?? null) : null;
    }

    public function getCanaries(string $signatureId, string $modelId): array {
        $key = $this->pairKey($signatureId, $modelId);
        $versions = $this->canaries[$key] ?? [];
        $result = [];
        foreach ($versions as $v) {
            $p = $this->store[$this->tripleKey($signatureId, $modelId, $v)] ?? null;
            if ($p) {
                $result[] = $p;
            }
        }
        return $result;
    }

    public function publish(PromptPreset $preset): void {
        $this->store[$this->tripleKey($preset->signatureId, $preset->modelId, $preset->version)] = $preset;
    }

    public function activate(string $signatureId, string $modelId, string $version): void {
        $this->active[$this->pairKey($signatureId, $modelId)] = $version;
    }

    // Helpers
    private function pairKey(string $sig, string $model): string {
        return $sig . '|' . $model;
    }

    private function tripleKey(string $sig, string $model, string $ver): string {
        return $sig . '|' . $model . '|' . $ver;
    }
}

