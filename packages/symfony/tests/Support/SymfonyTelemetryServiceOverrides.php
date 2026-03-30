<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Symfony\Tests\Support;

use Closure;
use Cognesy\Telemetry\Domain\Contract\CanExportObservations;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

final class SymfonyTelemetryServiceOverrides
{
    public static function exporter(RecordingTelemetryExporter $exporter): Closure
    {
        $registered = SymfonyTestServiceRegistry::put($exporter);

        return static function (ContainerBuilder $container) use ($registered): void {
            if ($container->hasAlias(CanExportObservations::class)) {
                $container->removeAlias(CanExportObservations::class);
            }

            $container->setDefinition(CanExportObservations::class, (new Definition(RecordingTelemetryExporter::class))
                ->setFactory([SymfonyTestServiceRegistry::class, 'get'])
                ->setArguments([$registered])
                ->setPublic(true));
        };
    }
}
