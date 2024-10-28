<?php

namespace Cognesy\Instructor\Extras\Prompt\Data;

use Cognesy\Instructor\Extras\Prompt\Enums\FrontMatterFormat;
use Cognesy\Instructor\Extras\Prompt\Enums\TemplateType;
use Cognesy\Instructor\Utils\Settings;
use InvalidArgumentException;

class PromptEngineConfig
{
    public function __construct(
        public TemplateType $templateType = TemplateType::Twig,
        public string $resourcePath = '',
        public string $cachePath = '',
        public string $extension = 'twig',
        public array $frontMatterTags = [],
        public FrontMatterFormat $frontMatterFormat = FrontMatterFormat::Yaml,
        public array $metadata = [],
    ) {}

    public static function load(string $setting) : PromptEngineConfig {
        if (!Settings::has('prompt', "settings.$setting")) {
            throw new InvalidArgumentException("Unknown setting: $setting");
        }
        return new PromptEngineConfig(
            templateType: TemplateType::from(Settings::get('prompt', "settings.$setting.templateType")),
            resourcePath: Settings::get('prompt', "settings.$setting.resourcePath"),
            cachePath: Settings::get('prompt', "settings.$setting.cachePath"),
            extension: Settings::get('prompt', "settings.$setting.extension"),
            metadata: Settings::get('prompt', "settings.$setting.metadata", []),
        );
    }
}
