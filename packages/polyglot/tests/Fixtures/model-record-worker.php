<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Config\Config;
use Cognesy\Polyglot\Inference\Contracts\CanProcessInferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Polyglot\Inference\Models\ModelCatalog;
use Cognesy\Polyglot\Inference\Models\ModelProfile;

require $argv[1];

$catalog = null;
$coldRequests = $argv[4] === 'request';
$scopes = 0;
$driver = new class implements CanProcessInferenceRequest {
    public ?ModelProfile $profile = null;
    public int $requests = 0;

    public function makeResponseFor(InferenceRequest $request): InferenceResponse {
        if ($this->profile !== null && $this->profile !== $request->modelProfile()) {
            throw new RuntimeException('The worker rehydrated its selected model record.');
        }
        $this->profile = $request->modelProfile();
        $this->requests++;

        return InferenceResponse::empty();
    }

    public function makeStreamDeltasFor(InferenceRequest $request): iterable {
        throw new LogicException('This fixture executes non-streamed requests only.');
    }
};
$events = new EventDispatcher;
$durations = [];
foreach (range(1, 125) as $iteration) {
    $start = hrtime(true);
    if ($catalog === null || $coldRequests) {
        Config::flushSourceCache();
        $catalog = ModelCatalog::fromPaths($argv[2], $argv[3]);
        $driver->profile = null;
        $scopes++;
    }
    $runtime = new InferenceRuntime($driver, $events, $catalog, 'custom', 'selected');
    $runtime->create(new InferenceRequest)->response();
    $durations[] = (hrtime(true) - $start) / 1_000_000;
}

echo json_encode([
    'requests' => $driver->requests,
    'scopes' => $scopes,
    'source' => $driver->profile?->source,
    'peakBytes' => memory_get_peak_usage(true),
    'durationsMs' => $durations,
], JSON_THROW_ON_ERROR);
