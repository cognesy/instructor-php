<?php

namespace Cognesy\Utils\Template;

use Cognesy\Utils\Template\Contracts\CanHandleTemplate;
use Cognesy\Utils\Template\Data\TemplateEngineConfig;
use Cognesy\Utils\Template\Drivers\ArrowpipeDriver;
use Cognesy\Utils\Template\Drivers\BladeDriver;
use Cognesy\Utils\Template\Drivers\TwigDriver;
use Cognesy\Utils\Template\Enums\TemplateEngineType;
use Cognesy\Utils\Settings;
use InvalidArgumentException;

class TemplateLibrary
{
    private CanHandleTemplate $driver;
    private TemplateEngineConfig $config;

    public function __construct(
        string               $library = '',
        TemplateEngineConfig $config = null,
        CanHandleTemplate    $driver = null,
    ) {
        $this->config = $config ?? TemplateEngineConfig::load(
            library: $library ?: Settings::get('prompt', "defaultLibrary")
        );
        $this->driver = $driver ?? $this->makeDriver($this->config);
    }

    public function get(string $library): self {
        $this->config = TemplateEngineConfig::load($library);
        $this->driver = $this->makeDriver($this->config);
        return $this;
    }

    public function withConfig(TemplateEngineConfig $config): self {
        $this->config = $config;
        $this->driver = $this->makeDriver($config);
        return $this;
    }

    public function withDriver(CanHandleTemplate $driver): self {
        $this->driver = $driver;
        return $this;
    }

    public function config(): TemplateEngineConfig {
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

    private function makeDriver(TemplateEngineConfig $config): CanHandleTemplate {
        return match ($config->templateEngine) {
            TemplateEngineType::Twig => new TwigDriver($config),
            TemplateEngineType::Blade => new BladeDriver($config),
            TemplateEngineType::Arrowpipe => new ArrowpipeDriver($config),
            default => throw new InvalidArgumentException("Unknown driver: $config->templateEngine"),
        };
    }
}
