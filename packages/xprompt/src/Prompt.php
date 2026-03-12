<?php

declare(strict_types=1);

namespace Cognesy\Xprompt;

use Cognesy\Template\Config\TemplateEngineConfig;
use Cognesy\Template\Template;
use Cognesy\Utils\Markdown\FrontMatter;
use Stringable;

abstract class Prompt implements Stringable
{
    public string $model = '';
    public bool $isBlock = false;
    public string $templateFile = '';
    public ?string $templateDir = null;
    /** @var list<class-string<Prompt>> */
    public array $blocks = [];

    protected array $ctx = [];
    protected ?TemplateEngineConfig $config = null;

    // -- Static constructors ------------------------------------------

    public static function make(): static
    {
        return new static();
    }

    public static function with(mixed ...$ctx): static
    {
        $instance = new static();
        $instance->ctx = $ctx;
        return $instance;
    }

    // -- Config -------------------------------------------------------

    public function withConfig(TemplateEngineConfig $config): static
    {
        $clone = clone $this;
        $clone->config = $config;
        return $clone;
    }

    protected function resolveConfig(): TemplateEngineConfig
    {
        if ($this->config !== null) {
            return $this->config;
        }
        if ($this->templateDir !== null) {
            return TemplateEngineConfig::twig($this->templateDir);
        }
        return TemplateEngineConfig::twig();
    }

    // -- Rendering ----------------------------------------------------

    public function render(mixed ...$ctx): string
    {
        $merged = [...$this->ctx, ...$ctx];
        $content = $this->body(...$merged);
        return flatten($content, $merged);
    }

    public function __toString(): string
    {
        return $this->render();
    }

    // -- Override point ------------------------------------------------

    public function body(mixed ...$ctx): string|array|null
    {
        if ($this->templateFile !== '') {
            return $this->renderTemplate(...$ctx);
        }
        return null;
    }

    // -- Metadata & introspection -------------------------------------

    public function meta(): array
    {
        if ($this->templateFile === '') {
            return [];
        }
        $raw = $this->loadTemplateFile();
        return FrontMatter::parse($raw)->data();
    }

    public function variables(): array
    {
        if ($this->templateFile === '') {
            return [];
        }
        return $this->makeTemplate()->variables();
    }

    public function validationErrors(mixed ...$ctx): array
    {
        if ($this->templateFile === '') {
            return [];
        }
        $merged = [...$this->ctx, ...$ctx];
        return $this->makeTemplate()->withValues($merged)->validationErrors();
    }

    // -- Template rendering (private) ---------------------------------

    protected function renderTemplate(mixed ...$ctx): string
    {
        $blockRenders = $this->renderBlocks($ctx);
        $variables = [...$ctx, 'blocks' => $blockRenders];

        return $this->makeTemplate()
            ->withValues($variables)
            ->toText();
    }

    protected function makeTemplate(): Template
    {
        $raw = $this->loadTemplateFile();
        $parsed = FrontMatter::parse($raw);
        $config = $this->resolveConfig();
        return (new Template(config: $config))->withTemplateContent($parsed->document());
    }

    protected function loadTemplateFile(): string
    {
        $path = $this->resolveTemplatePath();
        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException("Template file not found: {$path}");
        }
        return $content;
    }

    protected function renderBlocks(array $ctx): array
    {
        $rendered = [];
        foreach ($this->blocks as $blockClass) {
            $instance = new $blockClass();
            $rendered[$this->shortName($blockClass)] = $instance->render(...$ctx);
        }
        return $rendered;
    }

    protected function resolveTemplatePath(): string
    {
        $resourcePath = $this->resolveConfig()->resourcePath;
        if ($resourcePath === '') {
            return $this->templateFile;
        }
        return rtrim($resourcePath, '/') . '/' . $this->templateFile;
    }

    protected function shortName(string $class): string
    {
        $pos = strrpos($class, '\\');
        return $pos === false ? $class : substr($class, $pos + 1);
    }
}
