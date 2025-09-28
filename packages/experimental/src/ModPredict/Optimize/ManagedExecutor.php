<?php declare(strict_types=1);

namespace Cognesy\Experimental\ModPredict\Optimize;

use Cognesy\Experimental\ModPredict\Optimize\Contracts\ExampleStore;
use Cognesy\Experimental\ModPredict\Optimize\Contracts\ObservationPolicy;
use Cognesy\Experimental\ModPredict\Optimize\Contracts\Redactor;
use Cognesy\Experimental\ModPredict\Optimize\Data\ObservationRecord;
use Cognesy\Experimental\ModPredict\Optimize\Data\PromptPreset;
use Psr\Log\LoggerInterface;

final class ManagedExecutor
{
    public function __construct(
        private PromptResolver $resolver,
        private ExampleStore $examples,
        private ObservationPolicy $policy,
        private Redactor $redactor,
        private ?\DateTimeZone $tz = null,
        private ?LoggerInterface $logger = null,
    ) {}

    /**
     * @param callable(array $args, ?PromptPreset $preset): mixed $runner
     */
    public function run(
        string $signatureId,
        string $modelId,
        array $args,
        callable $runner,
        string $predictorPath = '',
    ): mixed {
        $observe = $this->policy->shouldObserve($signatureId, $predictorPath, '');
        $selection = $this->resolver->resolve($signatureId, $modelId);
        $preset = $selection->preset;

        $start = microtime(true);
        try {
            $result = $runner($args, $preset);
            $latency = (int)((microtime(true) - $start) * 1000);
            if ($observe && $this->sample($signatureId)) {
                $this->record($signatureId, $modelId, $preset?->version, $args, $result, $latency, null);
            }
            return $result;
        } catch (\Throwable $e) {
            $latency = (int)((microtime(true) - $start) * 1000);
            if ($observe && $this->sample($signatureId)) {
                $this->record($signatureId, $modelId, $preset?->version, $args, null, $latency, $e);
            }
            throw $e;
        }
    }

    private function record(string $sig, string $model, ?string $ver, array $args, mixed $out, int $latency, ?\Throwable $err): void {
        $rec = new ObservationRecord(
            signatureId: $sig,
            modelId: $model,
            presetVersion: $ver,
            input: $this->redactor->redactInput($sig, $args),
            output: is_null($out) ? null : $this->redactor->redactOutput($sig, $out),
            acceptance: 'unknown',
            latencyMs: $latency,
            tokenUsage: null,
            error: $err ? ['code' => 0, 'message' => $err->getMessage()] : null,
            context: [],
            observedAt: new \DateTimeImmutable('now', $this->tz ?? new \DateTimeZone('UTC')),
        );
        $this->examples->add($rec);
    }

    private function sample(string $sig): bool {
        $p = max(0.0, min(1.0, $this->policy->sampleRate($sig)));
        return mt_rand() / mt_getrandmax() <= $p;
    }
}

