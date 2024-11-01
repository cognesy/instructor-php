<?php

namespace Cognesy\Instructor\Extras\Prompt\Data;

use Cognesy\Instructor\Extras\Prompt\Enums\FrontMatterFormat;
use Cognesy\Instructor\Extras\Prompt\Enums\TemplateEngine;
use Cognesy\Instructor\Utils\Settings;
use InvalidArgumentException;

class PromptEngineConfig
{
    public function __construct(
        public TemplateEngine    $templateEngine = TemplateEngine::Twig,
        public string            $resourcePath = '',
        public string            $cachePath = '',
        public string            $extension = 'twig',
        public array             $frontMatterTags = [],
        public FrontMatterFormat $frontMatterFormat = FrontMatterFormat::Yaml,
        public array             $metadata = [],
    ) {}

    public static function load(string $library) : PromptEngineConfig {
        if (!Settings::has('prompt', "libraries.$library")) {
            throw new InvalidArgumentException("Unknown prompt library: $library");
        }
        return new PromptEngineConfig(
            templateEngine: TemplateEngine::from(Settings::get('prompt', "libraries.$library.templateEngine")),
            resourcePath: Settings::get('prompt', "libraries.$library.resourcePath"),
            cachePath: Settings::get('prompt', "libraries.$library.cachePath"),
            extension: Settings::get('prompt', "libraries.$library.extension"),
            metadata: Settings::get('prompt', "libraries.$library.metadata", []),
        );
    }

    public static function twig(string $resourcePath = '', string $cachePath = '') : PromptEngineConfig {
        $cachePath = $cachePath ?: '/tmp/instructor/twig';
        return new PromptEngineConfig(
            templateEngine: TemplateEngine::Twig,
            resourcePath: $resourcePath,
            cachePath: $cachePath,
            extension: '.twig',
        );
    }

    public static function blade(string $resourcePath = '', string $cachePath = '') : PromptEngineConfig {
        $cachePath = $cachePath ?: '/tmp/instructor/blade';
        return new PromptEngineConfig(
            templateEngine: TemplateEngine::Blade,
            resourcePath: $resourcePath,
            cachePath: $cachePath,
            extension: '.blade.php',
        );
    }
}
