<?php declare(strict_types=1);

namespace Cognesy\Template\Config;

use Cognesy\Config\BasePath;
use Cognesy\Config\Config;
use Cognesy\Template\Enums\FrontMatterFormat;
use Cognesy\Template\Enums\TemplateEngineType;
use InvalidArgumentException;
use Throwable;

class TemplateEngineConfig
{
    public const CONFIG_GROUP = 'prompt';

    private const PRESET_PATHS = [
        'config/prompt/presets',
        'packages/templates/resources/config/prompt/presets',
        'vendor/cognesy/instructor-php/packages/templates/resources/config/prompt/presets',
        'vendor/cognesy/instructor-templates/resources/config/prompt/presets',
    ];

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

    public static function fromPreset(string $preset, ?string $basePath = null): self {
        $basePaths = $basePath !== null ? [$basePath] : self::PRESET_PATHS;
        $resolvedPaths = BasePath::resolveExisting(...$basePaths);
        if ($resolvedPaths === []) {
            throw new InvalidArgumentException("No preset directory found for '{$preset}'. Searched: " . implode(', ', $basePaths));
        }
        $data = Config::fromPaths(...$resolvedPaths)
            ->load("{$preset}.yaml")
            ->toArray();
        return self::fromArray($data);
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

    public function withOverrides(array $values) : self {
        $config = array_merge($this->toArray(), $values);
        return self::fromArray($config);
    }

    public static function fromArray(array $config) : TemplateEngineConfig {
        try {
            $config['templateEngine'] = match(true) {
                !isset($config['templateEngine']) => TemplateEngineType::Twig,
                is_string($config['templateEngine']) => TemplateEngineType::fromText($config['templateEngine']),
                $config['templateEngine'] instanceof TemplateEngineType => $config['templateEngine'],
                default => TemplateEngineType::Twig,
            };
            $config['frontMatterFormat'] = match(true) {
                !isset($config['frontMatterFormat']) => FrontMatterFormat::Yaml,
                is_string($config['frontMatterFormat']) => FrontMatterFormat::from($config['frontMatterFormat'] ?? FrontMatterFormat::Yaml->value),
                $config['frontMatterFormat'] instanceof FrontMatterFormat => $config['frontMatterFormat'],
                default => FrontMatterFormat::Yaml,
            };
            $instance = new self(...$config);
        } catch (Throwable $e) {
            $data = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            throw new InvalidArgumentException(
                message: "Invalid configuration for TemplateEngineConfig: {$e->getMessage()}\nData: {$data}",
                previous: $e,
            );
        }
        return $instance;
    }
}
