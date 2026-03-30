<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Symfony\Tests\Support;

use Closure;
use Cognesy\Instructor\Contracts\CanCreateStructuredOutput;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\Embeddings\Contracts\CanCreateEmbeddings;
use Cognesy\Polyglot\Embeddings\Embeddings;
use Cognesy\Polyglot\Inference\Contracts\CanCreateInference;
use Cognesy\Polyglot\Inference\Inference;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class SymfonyCoreServiceOverrides
{
    public static function inference(InferenceFakeRuntime $fake): Closure
    {
        $serviceId = SymfonyTestServiceRegistry::put($fake);

        return static function (ContainerBuilder $container) use ($serviceId): void {
            self::replaceDefinition(
                $container,
                CanCreateInference::class,
                (new Definition(InferenceFakeRuntime::class))
                    ->setFactory([SymfonyTestServiceRegistry::class, 'get'])
                    ->setArguments([$serviceId])
                    ->setPublic(true),
            );
            self::replaceDefinition(
                $container,
                Inference::class,
                (new Definition(Inference::class))
                    ->setFactory([Inference::class, 'fromRuntime'])
                    ->setArguments([new Reference(CanCreateInference::class)])
                    ->setPublic(true),
            );
        };
    }

    public static function embeddings(EmbeddingsFakeRuntime $fake): Closure
    {
        $serviceId = SymfonyTestServiceRegistry::put($fake);

        return static function (ContainerBuilder $container) use ($serviceId): void {
            self::replaceDefinition(
                $container,
                CanCreateEmbeddings::class,
                (new Definition(EmbeddingsFakeRuntime::class))
                    ->setFactory([SymfonyTestServiceRegistry::class, 'get'])
                    ->setArguments([$serviceId])
                    ->setPublic(true),
            );
            self::replaceDefinition(
                $container,
                Embeddings::class,
                (new Definition(Embeddings::class))
                    ->setFactory([Embeddings::class, 'fromRuntime'])
                    ->setArguments([new Reference(CanCreateEmbeddings::class)])
                    ->setPublic(true),
            );
        };
    }

    public static function structuredOutput(StructuredOutputFakeRuntime $fake): Closure
    {
        $serviceId = SymfonyTestServiceRegistry::put($fake);

        return static function (ContainerBuilder $container) use ($serviceId): void {
            self::replaceDefinition(
                $container,
                CanCreateStructuredOutput::class,
                (new Definition(StructuredOutputFakeRuntime::class))
                    ->setFactory([SymfonyTestServiceRegistry::class, 'get'])
                    ->setArguments([$serviceId])
                    ->setPublic(true),
            );
            self::replaceDefinition(
                $container,
                StructuredOutput::class,
                (new Definition(StructuredOutput::class))
                    ->setArguments([new Reference(CanCreateStructuredOutput::class)])
                    ->setPublic(true),
            );
        };
    }

    private static function replaceDefinition(ContainerBuilder $container, string $id, Definition $definition): void
    {
        if ($container->hasAlias($id)) {
            $container->removeAlias($id);
        }

        $container->setDefinition($id, $definition);
    }
}
