<?php

namespace Cognesy\Instructor\Extras\Prompt;

use Cognesy\Instructor\Extras\Prompt\Contracts\CanHandleTemplate;
use Cognesy\Instructor\Extras\Prompt\Data\PromptEngineConfig;
use Cognesy\Instructor\Extras\Prompt\Drivers\BladeDriver;
use Cognesy\Instructor\Extras\Prompt\Drivers\TwigDriver;
use Cognesy\Instructor\Extras\Prompt\Enums\TemplateEngine;
use Cognesy\Instructor\Utils\Settings;
use InvalidArgumentException;

class PromptLibrary
{
    private CanHandleTemplate $driver;
    private PromptEngineConfig $config;

    public function __construct(
        string             $library = '',
        PromptEngineConfig $config = null,
        CanHandleTemplate  $driver = null,
    ) {
        $this->config = $config ?? PromptEngineConfig::load(
            library: $library ?: Settings::get('prompt', "defaultLibrary")
        );
        $this->driver = $driver ?? $this->makeDriver($this->config);
    }

    public function get(string $library): self {
        $this->config = PromptEngineConfig::load($library);
        $this->driver = $this->makeDriver($this->config);
        return $this;
    }

    public function withConfig(PromptEngineConfig $config): self {
        $this->config = $config;
        $this->driver = $this->makeDriver($config);
        return $this;
    }

    public function withDriver(CanHandleTemplate $driver): self {
        $this->driver = $driver;
        return $this;
    }

    public function config(): PromptEngineConfig {
        return $this->config;
    }

    public function driver(): CanHandleTemplate {
        return $this->driver;
    }

    public function loadTemplate(string $path): string {
        return $this->driver->getTemplateContent($path);
    }

    public function renderString(string $path, array $variables): string {
        return $this->driver->renderString($path, $variables);
    }

    public function renderFile(string $path, array $variables): string {
        return $this->driver->renderFile($path, $variables);
    }

    public function getVariableNames(string $content): array {
        return $this->driver->getVariableNames($content);
    }

    // INTERNAL ///////////////////////////////////////////////////

    private function makeDriver(PromptEngineConfig $config): CanHandleTemplate {
        return match ($config->templateEngine) {
            TemplateEngine::Twig => new TwigDriver($config),
            TemplateEngine::Blade => new BladeDriver($config),
            default => throw new InvalidArgumentException("Unknown driver: $config->templateEngine"),
        };
    }
}