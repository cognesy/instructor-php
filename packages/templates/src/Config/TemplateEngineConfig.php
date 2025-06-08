<?php

namespace Cognesy\Template\Config;

use Cognesy\Template\Enums\FrontMatterFormat;
use Cognesy\Template\Enums\TemplateEngineType;

class TemplateEngineConfig
{
    public const CONFIG_GROUP = 'prompt';

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

    public static function group() : string {
        return self::CONFIG_GROUP;
    }

    public function toArray() : array {
        return [
            'templateEngine' => $this->templateEngine->value,
            'resourcePath' => $this->resourcePath,
            'cachePath' => $this->cachePath,
            'extension' => $this->extension,
            'frontMatterTags' => $this->frontMatterTags,
            'frontMatterFormat' => $this->frontMatterFormat->value,
            'metadata' => $this->metadata,
        ];
    }

    public static function fromArray(array $data) : TemplateEngineConfig {
        return new TemplateEngineConfig(
            templateEngine: TemplateEngineType::from($data['templateEngine']),
            resourcePath: $data['resourcePath'] ?? '',
            cachePath: $data['cachePath'] ?? '',
            extension: $data['extension'] ?? 'twig',
            frontMatterTags: $data['frontMatterTags'] ?? [],
            frontMatterFormat: FrontMatterFormat::from($data['frontMatterFormat'] ?? FrontMatterFormat::Yaml->value),
            metadata: $data['metadata'] ?? [],
        );
    }
}
