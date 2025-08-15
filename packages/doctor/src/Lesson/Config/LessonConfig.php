<?php declare(strict_types=1);

namespace Cognesy\Doctor\Lesson\Config;

class LessonConfig
{
    public function __construct(
        public readonly string $llmPreset = 'anthropic',
        public readonly int $maxTokens = 2000,
        public readonly string $templatesDirectory = 'packages/doctor/templates',
        public readonly string $templateName = 'lesson',
    ) {}
}