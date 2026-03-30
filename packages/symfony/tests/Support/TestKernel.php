<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Symfony\Tests\Support;

use Closure;
use Cognesy\Instructor\Symfony\InstructorSymfonyBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

final class TestKernel extends Kernel
{
    /**
     * @param array<string,mixed> $instructorConfig
     * @param array<string,mixed> $frameworkConfig
     * @param list<Closure(ContainerBuilder):void> $containerConfigurators
     */
    public function __construct(
        private readonly array $instructorConfig,
        private readonly array $frameworkConfig,
        private readonly array $containerConfigurators,
        private readonly string $workspaceDir,
    ) {
        parent::__construct('test', false);
    }

    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle,
            new InstructorSymfonyBundle,
        ];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container): void {
            $container->loadFromExtension('framework', array_replace_recursive(
                $this->defaultFrameworkConfig(),
                $this->frameworkConfig,
            ));
            $container->loadFromExtension('instructor', $this->instructorConfig);
        });
    }

    public function build(ContainerBuilder $container): void
    {
        foreach ($this->containerConfigurators as $configurator) {
            $configurator($container);
        }
    }

    public function getCacheDir(): string
    {
        return $this->workspaceDir.'/cache';
    }

    public function getLogDir(): string
    {
        return $this->workspaceDir.'/log';
    }

    private function defaultFrameworkConfig(): array
    {
        return [
            'secret' => 'instructor-symfony-tests',
            'test' => true,
            'http_method_override' => false,
            'http_client' => [],
        ];
    }
}
