<?php declare(strict_types=1);

namespace Cognesy\Doctor\Lesson\Services;

use Cognesy\Config\BasePath;
use Cognesy\Doctor\Lesson\Config\LessonConfig;
use Cognesy\InstructorHub\Data\Example;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Template\Config\TemplateEngineConfig;
use Cognesy\Template\Template;

class LessonService
{
    public function __construct(
        private readonly LessonConfig $config
    ) {}

    public function generateLesson(Example $example): string
    {
        $prompt = $this->buildPrompt($example->title, $example->content);
        
        return (new Inference)
            ->using($this->config->llmPreset)
            ->withMessages($prompt)
            ->withMaxTokens($this->config->maxTokens)
            ->get();
    }

    private function buildPrompt(string $exampleTitle, string $codeContent): string
    {
        $templatesDir = BasePath::get($this->config->templatesDirectory);
        $config = TemplateEngineConfig::arrowpipe(resourcePath: $templatesDir);

        return (new Template())
            ->withConfig($config)
            ->withTemplate($this->config->templateName)
            ->withValues([
                'example_title' => $exampleTitle,
                'code_content' => $codeContent,
            ])
            ->toText();
    }
}