<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Utils\Web\Html;

use InvalidArgumentException;

class HtmlPipeline
{
    private string $passable;
    private array $pipes = [];
    private string $method = 'process';

    public function __construct(string $passable)
    {
        $this->passable = $passable;
    }

    public static function send(string $passable): self
    {
        return new static($passable);
    }

    public function through(array $pipes): self
    {
        $this->pipes = $pipes;
        return $this;
    }

    public function via(string $method): self
    {
        $this->method = $method;
        return $this;
    }

    public function thenReturn(): string
    {
        return array_reduce($this->pipes, function ($passable, $pipe) {
            if (is_array($pipe) && count($pipe) === 2) {
                [$class, $method] = $pipe;
                return $class->$method($passable);
            }

            if (is_object($pipe)) {
                return $pipe->{$this->method}($passable);
            }

            if (is_callable($pipe)) {
                return $pipe($passable);
            }

            throw new InvalidArgumentException('Invalid pipe provided');
        }, $this->passable);
    }
}