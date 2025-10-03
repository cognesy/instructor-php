<?php declare(strict_types=1);

namespace Cognesy\Template\Drivers;

use Cognesy\Config\BasePath;
use Cognesy\Template\Config\TemplateEngineConfig;
use Cognesy\Template\Contracts\CanHandleTemplate;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\Node\Expression\NameExpression;
use Twig\Node\Node;
use Twig\Source;

/**
 * Class TwigDriver
 *
 * Handles the rendering of Twig templates with custom file extensions and front matter support.
 */
class TwigDriver implements CanHandleTemplate
{
    private Environment $twig;

    /**
     * TwigDriver constructor.
     *
     * @param \Cognesy\Template\Config\TemplateEngineConfig $config The configuration for the prompt engine
     */
    public function __construct(
        private TemplateEngineConfig $config,
    ) {
        $resourcePath = $this->config->resourcePath ?: '.';
        try {
            $paths = [BasePath::get($resourcePath)];
        } catch (\Exception $e) {
            // Fallback to current directory if BasePath fails
            $paths = [getcwd() ?: '.'];
        }
        $extension = $this->config->extension;

        $loader = new class(
            paths: $paths,
            fileExtension: $extension
        ) extends FilesystemLoader {
            private string $fileExtension;

            /**
             * Constructor for the custom FilesystemLoader.
             *
             * @param array $paths The paths where templates are stored
             * @param string|null $rootPath The root path for templates
             * @param string $fileExtension The file extension to use for templates
             */
            public function __construct(
                $paths = [],
                ?string $rootPath = null,
                string $fileExtension = '',
            ) {
                // Ensure paths is always an array and filter out empty values
                $paths = array_filter((array) $paths, function($path) {
                    return !empty($path) && is_string($path);
                });
                
                // Ensure rootPath is never null to avoid realpath() deprecation warning
                $rootPath = $rootPath ?? (getcwd() ?: '.');
                
                parent::__construct($paths, $rootPath);
                $this->fileExtension = $fileExtension;
            }

            /**
             * Finds a template by its name and appends the file extension if not present.
             *
             * @param string $name The name of the template
             * @param bool $throw Whether to throw an exception if the template is not found
             * @return string The path to the template
             */
            #[\Override]
            protected function findTemplate(string $name, bool $throw = true): string {
                if (pathinfo($name, PATHINFO_EXTENSION) === '') {
                    $name .= $this->fileExtension;
                }
                $result = parent::findTemplate($name, $throw);
                if ($result === null) {
                    throw new \RuntimeException("Template not found: $name");
                }
                return $result;
            }
        };

        $this->twig = new Environment(
            loader: $loader,
            options: ['cache' => $this->config->cachePath],
        );
    }

    /**
     * Renders a template file with the given parameters.
     *
     * @param string $path The name of the template file
     * @param array $parameters The parameters to pass to the template
     * @return string The rendered template
     */
    #[\Override]
    public function renderFile(string $path, array $parameters = []): string {
        return $this->twig->render($path, $parameters);
    }

    /**
     * Renders a template from a string with the given parameters.
     *
     * @param string $content The template content as a string
     * @param array $parameters The parameters to pass to the template
     * @return string The rendered template
     */
    #[\Override]
    public function renderString(string $content, array $parameters = []): string {
        return $this->twig->createTemplate($content)->render($parameters);
    }

    /**
     * Gets the content of a template file.
     *
     * @param string $path
     * @return string
     */
    #[\Override]
    public function getTemplateContent(string $path): string {
        return $this->twig->getLoader()->getSourceContext($path)->getCode();
    }

    /**
     * Gets names of variables used in a template content.
     *
     * @param string $content
     * @return array
     * @throws \Twig\Error\SyntaxError
     */
    #[\Override]
    public function getVariableNames(string $content): array {
        // make Twig Source from content string
        $source = new Source($content, 'template');
        // Parse the template to get its AST
        $parsedTemplate = $this->twig->parse($this->twig->tokenize($source));
        // Collect variables
        $variables = $this->findVariables($parsedTemplate);
        // Remove duplicates
        return array_unique($variables);
    }

    // INTERNAL /////////////////////////////////////////////////

    private function findVariables(Node $node): array {
        $variables = [];
        // Check for variable nodes and add them to the list
        if ($node instanceof NameExpression) {
            $variables[] = $node->getAttribute('name');
        }
        // Recursively search in child nodes
        foreach ($node as $child) {
            $childVariables = $this->findVariables($child);
            foreach ($childVariables as $variable) {
                $variables[] = $variable;
            }
        }
        return $variables;
    }
}
