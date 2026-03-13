<?php declare(strict_types=1);

namespace Cognesy\Doctools\Lesson\Config;

class LessonConfig
{
    public function __construct(
        public readonly string $llmDriver = 'anthropic',
        public readonly int $maxTokens = 2000,
        public readonly string $templatesDirectory = 'packages/doctools/resources/templates',
        public readonly string $templateName = 'lesson',
    ) {}
}
