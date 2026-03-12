<?php

declare(strict_types=1);

namespace Cognesy\Xprompt;

use Cognesy\Template\Config\TemplateEngineConfig;
use Cognesy\Xprompt\Attributes\AsPrompt;
use InvalidArgumentException;
use ReflectionClass;
use RuntimeException;

class PromptRegistry
{
    /** @var array<string, class-string<Prompt>> name -> default class */
    private array $registry = [];

    /** @var array<string, array<string, class-string<Prompt>>> name -> [className -> class] */
    private array $variants = [];

    public function __construct(
        private ?TemplateEngineConfig $config = null,
        /** @var array<string, class-string<Prompt>> */
        private array $overrides = [],
    ) {}

    /**
     * Register a Prompt class under a name.
     * If the name already exists, the class is stored as a variant.
     */
    public function register(string $name, string $class): void
    {
        if (!is_subclass_of($class, Prompt::class)) {
            throw new InvalidArgumentException(
                "Class {$class} must extend " . Prompt::class
            );
        }

        if (!isset($this->registry[$name])) {
            $this->registry[$name] = $class;
        }

        // Always track in variants (including the default)
        $shortName = $this->shortName($class);
        $this->variants[$name][$shortName] = $class;
    }

    /**
     * Register a class using its #[AsPrompt] attribute name.
     */
    public function registerClass(string $class): void
    {
        $name = $this->resolveNameFromClass($class);
        if ($name === null) {
            throw new InvalidArgumentException(
                "Class {$class} has no #[AsPrompt] attribute or \$promptName property"
            );
        }
        $this->register($name, $class);
    }

    /**
     * Get a Prompt instance by name, applying overrides and config if configured.
     */
    public function get(string $name): Prompt
    {
        if (!isset($this->registry[$name])) {
            throw new RuntimeException("Unknown prompt: '{$name}'");
        }

        $class = $this->resolveClass($name);
        $instance = new $class();

        if ($this->config !== null) {
            $instance = $instance->withConfig($this->config);
        }

        return $instance;
    }

    public function has(string $name): bool
    {
        return isset($this->registry[$name]);
    }

    /**
     * @return list<string>
     */
    public function names(bool $includeBlocks = false): array
    {
        if ($includeBlocks) {
            return array_keys($this->registry);
        }

        return array_keys(array_filter(
            $this->registry,
            fn(string $class): bool => !(new $class())->isBlock,
        ));
    }

    /**
     * @return iterable<string, class-string<Prompt>>
     */
    public function all(bool $includeBlocks = false): iterable
    {
        foreach ($this->registry as $name => $class) {
            if (!$includeBlocks && (new $class())->isBlock) {
                continue;
            }
            yield $name => $class;
        }
    }

    /**
     * @return array<string, class-string<Prompt>>
     */
    public function variants(string $name): array
    {
        return $this->variants[$name] ?? [];
    }

    // -- Private --------------------------------------------------------

    /**
     * @return class-string<Prompt>
     */
    private function resolveClass(string $name): string
    {
        if (!isset($this->overrides[$name])) {
            return $this->registry[$name];
        }

        $override = $this->overrides[$name];
        $variants = $this->variants[$name] ?? [];

        // Try by short class name first
        $shortName = $this->shortName($override);
        if (isset($variants[$shortName])) {
            return $variants[$shortName];
        }

        // Try by full class name
        foreach ($variants as $variantClass) {
            if ($variantClass === $override) {
                return $variantClass;
            }
        }

        throw new RuntimeException(
            "Override '{$override}' for prompt '{$name}' is not registered as a variant"
        );
    }

    private function resolveNameFromClass(string $class): ?string
    {
        $ref = new ReflectionClass($class);

        // Check #[AsPrompt] attribute
        $attrs = $ref->getAttributes(AsPrompt::class);
        if ($attrs !== []) {
            return $attrs[0]->newInstance()->name;
        }

        // Check $promptName property
        if ($ref->hasProperty('promptName')) {
            $prop = $ref->getProperty('promptName');
            if ($prop->isPublic() && $prop->isDefault()) {
                return $prop->getDefaultValue();
            }
        }

        return null;
    }

    private function shortName(string $class): string
    {
        $pos = strrpos($class, '\\');
        return $pos === false ? $class : substr($class, $pos + 1);
    }
}
