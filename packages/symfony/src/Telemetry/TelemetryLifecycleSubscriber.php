<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Symfony\Telemetry;

use Cognesy\Telemetry\Domain\Contract\CanFlushTelemetry;
use Cognesy\Telemetry\Domain\Contract\CanShutdownTelemetry;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerStoppedEvent;

final readonly class TelemetryLifecycleSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private CanFlushTelemetry $flushTelemetry,
        private CanShutdownTelemetry $shutdownTelemetry,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::TERMINATE => 'onHttpTerminate',
            ConsoleEvents::TERMINATE => 'onConsoleTerminate',
            ConsoleEvents::ERROR => 'onConsoleError',
            WorkerMessageHandledEvent::class => 'onWorkerMessageHandled',
            WorkerMessageFailedEvent::class => 'onWorkerMessageFailed',
            WorkerStoppedEvent::class => 'onWorkerStopped',
        ];
    }

    public function onHttpTerminate(TerminateEvent $event): void
    {
        $this->flushAndShutdown();
    }

    public function onConsoleTerminate(ConsoleTerminateEvent $event): void
    {
        $this->flushAndShutdown();
    }

    public function onConsoleError(ConsoleErrorEvent $event): void
    {
        $this->flushAndShutdown();
    }

    public function onWorkerMessageHandled(WorkerMessageHandledEvent $event): void
    {
        $this->flushTelemetry->flush();
    }

    public function onWorkerMessageFailed(WorkerMessageFailedEvent $event): void
    {
        $this->flushTelemetry->flush();
    }

    public function onWorkerStopped(WorkerStoppedEvent $event): void
    {
        $this->flushAndShutdown();
    }

    private function flushAndShutdown(): void
    {
        $this->flushTelemetry->flush();
        $this->shutdownTelemetry->shutdown();
    }
}
