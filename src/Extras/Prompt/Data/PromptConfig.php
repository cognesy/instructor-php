<?php

namespace Cognesy\Instructor\Extras\Prompt\Data;

use Cognesy\Instructor\Extras\Prompt\Enums\TemplateType;
use Cognesy\Instructor\Utils\Settings;
use InvalidArgumentException;

class PromptConfig
{
    public function __construct(
        public TemplateType $templateType = TemplateType::Twig,
        public string $resourcePath = '',
        public string $extension = 'twig',
    ) {}

    public static function load(string $client) : PromptConfig {
        if (!Settings::has('prompt', "engines.$client")) {
            throw new InvalidArgumentException("Unknown engine: $client");
        }
        return new PromptConfig(
            templateType: TemplateType::from(Settings::get('prompt', "engines.$client.templateType")),
            resourcePath: Settings::get('prompt', "engines.$client.resourcePath"),
            extension: Settings::get('prompt', "engines.$client.extension", 'twig'),
        );
    }
}
