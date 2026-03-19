<?php declare(strict_types=1);

use Cognesy\Config\Env;
use Cognesy\Telemetry\Adapters\Langfuse\LangfuseConfig;
use Cognesy\Telemetry\Adapters\Langfuse\LangfuseExporter;
use Cognesy\Telemetry\Adapters\Langfuse\LangfuseHttpTransport;
use Cognesy\Telemetry\Application\Registry\TraceRegistry;
use Cognesy\Telemetry\Application\Telemetry;
use Cognesy\Telemetry\Domain\Value\AttributeBag;
use Cognesy\Telemetry\Tests\Integration\Support\InteropEnv;
use Cognesy\Telemetry\Tests\Integration\Support\InteropRun;
use Cognesy\Telemetry\Tests\Integration\Support\LangfuseQueryClient;
use Cognesy\Telemetry\Tests\Integration\Support\Polling;

it('exports direct telemetry to langfuse and can query it back', function () {
    InteropEnv::requireLangfuse();

    $run = InteropRun::fresh('langfuse');
    $traceName = $run->marker('interop.langfuse.root');
    $client = LangfuseQueryClient::fromEnv();
    $telemetry = new Telemetry(
        registry: new TraceRegistry(),
        exporter: new LangfuseExporter(
            transport: new LangfuseHttpTransport(new LangfuseConfig(
                baseUrl: (string) Env::get('LANGFUSE_BASE_URL', ''),
                publicKey: (string) Env::get('LANGFUSE_PUBLIC_KEY', ''),
                secretKey: (string) Env::get('LANGFUSE_SECRET_KEY', ''),
            )),
        ),
    );

    $telemetry->openRoot(
        key: 'run',
        name: $traceName,
        attributes: AttributeBag::empty()->with('interop.run_id', $run->id()),
    );
    $telemetry->log(
        key: 'run',
        name: $run->marker('interop.langfuse.log'),
        attributes: AttributeBag::empty()->with('interop.marker', $run->marker('marker')),
    );
    $telemetry->complete('run');
    $telemetry->flush();

    $trace = Polling::eventually(
        probe: static fn(): ?array => $client->latestTraceNamed($traceName),
        timeoutSeconds: 60,
    );

    expect($trace)->not->toBeNull()
        ->and($trace['name'] ?? null)->toBe($traceName);
})->group('integration', 'interop', 'langfuse');
