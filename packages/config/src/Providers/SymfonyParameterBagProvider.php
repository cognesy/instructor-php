<?php

namespace Cognesy\Config\Providers;

use Adbar\Dot;
use Cognesy\Config\Contracts\CanProvideConfig;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class SymfonyParameterBagProvider implements CanProvideConfig
{
    private ParameterBagInterface $parameterBag;
    private ?Dot $dot = null;

    public function __construct(ParameterBagInterface $parameterBag)
    {
        $this->parameterBag = $parameterBag;
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
            $this->dot = new Dot($this->parameterBag->all());
        }

        return $this->dot;
    }
}