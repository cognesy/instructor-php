<?php

namespace Cognesy\Template\Data;

use Cognesy\Template\Enums\FrontMatterFormat;
use Cognesy\Template\Enums\TemplateEngineType;

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
