<?php declare(strict_types=1);

use Cognesy\Config\Env;
use Cognesy\Telemetry\Adapters\Logfire\LogfireConfig;
use Cognesy\Telemetry\Adapters\Logfire\LogfireExporter;
use Cognesy\Telemetry\Application\Registry\TraceRegistry;
use Cognesy\Telemetry\Application\Telemetry;
use Cognesy\Telemetry\Domain\Value\AttributeBag;
use Cognesy\Telemetry\Tests\Integration\Support\InteropEnv;
use Cognesy\Telemetry\Tests\Integration\Support\InteropRun;
use Cognesy\Telemetry\Tests\Integration\Support\LogfireQueryClient;
use Cognesy\Telemetry\Tests\Integration\Support\Polling;

it('exports direct telemetry to logfire and can query it back', function () {
    InteropEnv::requireLogfire();

    $run = InteropRun::fresh('logfire');
    $serviceName = $run->serviceName('tests.telemetry.logfire');
    $client = LogfireQueryClient::fromEnv();
    $telemetry = new Telemetry(
        registry: new TraceRegistry(),
        exporter: new LogfireExporter(new LogfireConfig(
            endpoint: rtrim((string) Env::get('LOGFIRE_OTLP_ENDPOINT', ''), '/'),
            serviceName: $serviceName,
            headers: ['Authorization' => (string) Env::get('LOGFIRE_TOKEN', '')],
        )),
    );

    $telemetry->openRoot('run', $run->marker('interop.logfire.root'));
    $telemetry->log(
        key: 'run',
        name: $run->marker('interop.logfire.log'),
        attributes: AttributeBag::empty()->with('interop.run_id', $run->id()),
    );
    $telemetry->complete('run');
    $telemetry->flush();

    $timestamp = Polling::eventually(
        probe: static fn(): ?string => $client->latestTimestampForService($serviceName),
    );

    expect($timestamp)->not->toBeNull();
})->group('integration', 'interop', 'logfire');
