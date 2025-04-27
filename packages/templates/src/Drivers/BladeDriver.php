<?php

namespace Cognesy\Template\Drivers;

use Cognesy\Template\Contracts\CanHandleTemplate;
use Cognesy\Template\Data\TemplateEngineConfig;
use Cognesy\Utils\BasePath;
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
     * @param \Cognesy\Template\Data\TemplateEngineConfig $config The configuration for the prompt engine
     */
    public function __construct(
        private TemplateEngineConfig $config,
    ) {
        $views = BasePath::get($this->config->resourcePath);
        $cache = BasePath::get($this->config->cachePath);
        $extension = $this->config->extension;
        $mode = $this->config->metadata['mode'] ?? BladeOne::MODE_AUTO;
        $this->blade = new BladeOne($views, $cache, $mode);
        $this->blade->setFileExtension($extension);
    }

    /**
     * Renders a template file with the given parameters.
     *
     * @param string $path Library path of the template file
     * @param array $parameters The parameters to pass to the template
     * @return string The rendered template
     */
    public function renderFile(string $path, array $parameters = []): string {
        return $this->blade->run($path, $parameters);
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
     * @param string $path Library path of the template file
     * @return string Raw content of the template file
     */
    public function getTemplateContent(string $path): string {
        $templatePath = $this->blade->getTemplateFile($path);
        if (!file_exists($templatePath)) {
            throw new Exception("Template '$path' file does not exist: $templatePath");
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
