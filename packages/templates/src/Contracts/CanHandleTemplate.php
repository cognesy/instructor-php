<?php declare(strict_types=1);

namespace Cognesy\Template\Contracts;

/**
 * Interface CanHandleTemplate
 *
 * Defines the methods that a class must implement to be able to handle templates.
 */
interface CanHandleTemplate
{
    /**
     * Renders a template file with the given parameters.
     *
     * @param string $path Library path of the template file
     * @param array $parameters The parameters to pass to the template
     * @return string The rendered template
     */
    public function renderFile(string $path, array $parameters = []) : string;

    /**
     * Renders a template from a string with the given parameters.
     *
     * @param string $content The template content as a string
     * @param array $parameters The parameters to pass to the template
     * @return string The rendered template
     */
    public function renderString(string $content, array $parameters = []) : string;

    /**
     * Gets the content of a template file.
     *
     * @param string $path Library path of the template file
     * @return string The content of the template file
     */
    public function getTemplateContent(string $path): string;

    /**
     * Gets names of variables used in template content.
     *
     * @param string $content The content of the template
     * @return array Names of variables in the template
     */
    public function getVariableNames(string $content): array;
}
