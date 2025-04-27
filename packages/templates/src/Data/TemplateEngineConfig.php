<?php

namespace Cognesy\Template\Data;

use Cognesy\Template\Enums\FrontMatterFormat;
use Cognesy\Template\Enums\TemplateEngineType;
use Cognesy\Utils\Settings;
use InvalidArgumentException;

class TemplateEngineConfig
{
    public function __construct(
        public TemplateEngineType $templateEngine = TemplateEngineType::Twig,
        public string             $resourcePath = '',
        public string             $cachePath = '',
        public string             $extension = 'twig',
        public array              $frontMatterTags = [],
        public FrontMatterFormat  $frontMatterFormat = FrontMatterFormat::Yaml,
        public array              $metadata = [],
    ) {}

    public static function load(string $library) : TemplateEngineConfig {
        if (!Settings::has('prompt', "libraries.$library")) {
            throw new InvalidArgumentException("Unknown prompt library: $library");
        }
        return new TemplateEngineConfig(
            templateEngine: TemplateEngineType::from(Settings::get('prompt', "libraries.$library.templateEngine")),
            resourcePath: Settings::get('prompt', "libraries.$library.resourcePath"),
            cachePath: Settings::get('prompt', "libraries.$library.cachePath"),
            extension: Settings::get('prompt', "libraries.$library.extension"),
            metadata: Settings::get('prompt', "libraries.$library.metadata", []),
        );
    }

    public static function twig(string $resourcePath = '', string $cachePath = '') : TemplateEngineConfig {
        $cachePath = $cachePath ?: '/tmp/instructor/twig';
        return new TemplateEngineConfig(
            templateEngine: TemplateEngineType::Twig,
            resourcePath: $resourcePath,
            cachePath: $cachePath,
            extension: '.twig',
        );
    }

    public static function blade(string $resourcePath = '', string $cachePath = '') : TemplateEngineConfig {
        $cachePath = $cachePath ?: '/tmp/instructor/blade';
        return new TemplateEngineConfig(
            templateEngine: TemplateEngineType::Blade,
            resourcePath: $resourcePath,
            cachePath: $cachePath,
            extension: '.blade.php',
        );
    }

    public static function arrowpipe(string $resourcePath = '', string $cachePath = '') : TemplateEngineConfig {
        $cachePath = $cachePath ?: '/tmp/instructor/arrowpipe';
        return new TemplateEngineConfig(
            templateEngine: TemplateEngineType::Arrowpipe,
            resourcePath: $resourcePath,
            cachePath: $cachePath,
            extension: '.tpl',
        );
    }
}
