<?php

namespace Cognesy\Config\Providers;

use Adbar\Dot;
use Cognesy\Config\Contracts\CanProvideConfig;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SymfonyConfigProvider implements CanProvideConfig
{
    private ContainerInterface $container;
    private ?Dot $dot = null;

    public function __construct(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    public function get(string $path, mixed $default = null): mixed
    {
        return $this->getDot()->get($path, $default);
    }

    public function has(string $path): bool
    {
        return $this->getDot()->has($path);
    }

    private function getDot(): Dot
    {
        if ($this->dot === null) {
            $this->dot = new Dot($this->container->getParameterBag()->all());
        }

        return $this->dot;
    }
}