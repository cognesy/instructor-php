<?php declare(strict_types=1);

namespace Cognesy\Instructor\Laravel\Testing;

use Cognesy\Agents\Broadcasting\BroadcastConfig;
use Cognesy\Agents\Broadcasting\CanBroadcastAgentEvents;
use Cognesy\Agents\Capability\CanManageAgentCapabilities;
use Cognesy\Agents\Capability\Core\UseDriver;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Session\Contracts\CanManageAgentSessions;
use Cognesy\Agents\Session\Contracts\CanStoreSessions;
use Cognesy\Agents\Session\SessionRepository;
use Cognesy\Agents\Session\SessionRuntime;
use Cognesy\Agents\Session\Store\InMemorySessionStore;
use Cognesy\Instructor\Laravel\Agents\Broadcasting\LaravelAgentBroadcasting;
use Cognesy\Telemetry\Application\Telemetry;
use Cognesy\Telemetry\Application\Registry\TraceRegistry;
use Cognesy\Telemetry\Domain\Contract\CanExportObservations;
use Cognesy\Telemetry\Domain\Contract\CanFlushTelemetry;
use Cognesy\Telemetry\Domain\Contract\CanShutdownTelemetry;
use Illuminate\Container\Container;

final readonly class NativeAgentTesting
{
    public function __construct(
        private Container $app,
    ) {}

    public function fakeDriver(?FakeAgentDriver $driver = null, string $capabilityName = 'use_test_driver'): FakeAgentDriver
    {
        $resolved = $driver ?? new FakeAgentDriver();
        $this->app->instance(FakeAgentDriver::class, $resolved);
        $this->app->make(CanManageAgentCapabilities::class)->register($capabilityName, new UseDriver($resolved));

        return $resolved;
    }

    public function fakeSessions(?InMemorySessionStore $store = null): InMemorySessionStore
    {
        $resolved = $store ?? new InMemorySessionStore();
        $repository = new SessionRepository($resolved);
        $runtime = new SessionRuntime(
            sessions: $repository,
            events: $this->app->make(\Cognesy\Events\Contracts\CanHandleEvents::class),
        );

        $this->app->instance(InMemorySessionStore::class, $resolved);
        $this->app->instance(CanStoreSessions::class, $resolved);
        $this->app->instance(SessionRepository::class, $repository);
        $this->app->instance(SessionRuntime::class, $runtime);
        $this->app->instance(CanManageAgentSessions::class, $runtime);

        return $resolved;
    }

    public function fakeBroadcasts(?RecordingAgentEventTransport $transport = null): RecordingAgentEventTransport
    {
        $resolved = $transport ?? new RecordingAgentEventTransport();

        $this->app->instance(CanBroadcastAgentEvents::class, $resolved);
        $this->app->instance(LaravelAgentBroadcasting::class, new LaravelAgentBroadcasting(
            transport: $resolved,
            config: $this->app->make(BroadcastConfig::class),
        ));

        return $resolved;
    }

    public function captureTelemetry(?RecordingTelemetryExporter $exporter = null): RecordingTelemetryExporter
    {
        $resolved = $exporter ?? new RecordingTelemetryExporter();
        $telemetry = new Telemetry(
            registry: $this->app->make(TraceRegistry::class),
            exporter: $resolved,
        );

        $this->app->instance(CanExportObservations::class, $resolved);
        $this->app->instance(Telemetry::class, $telemetry);
        $this->app->instance(CanFlushTelemetry::class, $telemetry);
        $this->app->instance(CanShutdownTelemetry::class, $telemetry);

        return $resolved;
    }
}
