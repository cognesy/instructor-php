<?php

namespace Cognesy\Instructor\Extras\Prompt\Drivers;

use Cognesy\Instructor\Extras\Prompt\Contracts\CanHandleTemplate;
use Cognesy\Instructor\Extras\Prompt\Data\PromptEngineConfig;
use eftec\bladeone\BladeOne;
use Exception;

/**
 * Class BladeDriver
 *
 * Handles the rendering of Blade templates with custom file extensions and front matter support.
 */
class BladeDriver implements CanHandleTemplate
{
    private BladeOne $blade;

    /**
     * BladeDriver constructor.
     *
     * @param PromptEngineConfig $config The configuration for the prompt engine
     */
    public function __construct(
        private PromptEngineConfig $config,
    ) {
        $views = __DIR__ . $this->config->resourcePath;
        $cache = __DIR__ . $this->config->cachePath;
        $extension = $this->config->extension;
        $mode = $this->config->metadata['mode'] ?? BladeOne::MODE_AUTO;
        $this->blade = new BladeOne($views, $cache, $mode);
        $this->blade->setFileExtension($extension);
    }

    /**
     * Renders a template file with the given parameters.
     *
     * @param string $name The name of the template file
     * @param array $parameters The parameters to pass to the template
     * @return string The rendered template
     */
    public function renderFile(string $name, array $parameters = []): string {
        return $this->blade->run($name, $parameters);
    }

    /**
     * Renders a template from a string with the given parameters.
     *
     * @param string $content The template content as a string
     * @param array $parameters The parameters to pass to the template
     * @return string The rendered template
     */
    public function renderString(string $content, array $parameters = []): string {
        return $this->blade->runString($content, $parameters);
    }

    /**
     * Gets the content of a template file.
     *
     * @param string $name
     * @return string
     */
    public function getTemplateContent(string $name): string {
        $templatePath = $this->blade->getTemplateFile($name);
        if (!file_exists($templatePath)) {
            throw new Exception("Template '$name' file does not exist: $templatePath");
        }
        return file_get_contents($templatePath);
    }

    /**
     * Gets names of variables from a template content.
     * @param string $content
     * @return array
     */
    public function getVariableNames(string $content): array {
        $variables = [];
        preg_match_all('/{{\s*([$a-zA-Z0-9_]+)\s*}}/', $content, $matches);
        foreach ($matches[1] as $match) {
            $name = trim($match);
            $name = str_starts_with($name, '$') ? substr($name, 1) : $name;
            $variables[] = $name;
        }
        return array_unique($variables);
    }
}
