<?php
namespace Cognesy\Instructor\Extras\Prompt;

use Cognesy\Instructor\Extras\Prompt\Enums\TemplateEngine;
use Cognesy\Instructor\Utils\Json\Json;
use Cognesy\Instructor\Utils\Messages\Message;
use Cognesy\Instructor\Utils\Messages\Messages;
use InvalidArgumentException;

class Template
{
    private bool $clearUnknownParams;
    private array $parameters;

    private array $parameterValues = [];
    private array $parameterKeys = [];

    public function __construct(
        array $parameters = [],
        bool  $clearUnknownParams = true,
    ) {
        $this->clearUnknownParams = $clearUnknownParams;
        $this->parameters = $parameters;
        if (empty($parameters)) {
            return;
        }
        // remove keys starting with @ - these are used for section templates
        $filteredParameters = array_filter(
            $parameters,
            fn($key) => substr($key, 0, 1) !== '@',
            ARRAY_FILTER_USE_KEY
        );
        $materializedParameters = $this->materializeParameters($filteredParameters);
        $this->parameterValues = array_values($materializedParameters);
        $this->parameterKeys = array_map(
            fn($key) => $this->varPattern($key),
            array_keys($materializedParameters)
        );
    }

    public function getParameters() : array {
        return $this->parameters;
    }

    public static function cleanVarMarkers(string $template) : string {
        return str_replace(['<|', '|>'], '', $template);
    }

    public static function render(
        string $template,
        array  $parameters,
        bool   $clearUnknownParams = true,
    ) : string {
        return (new Template(
            $parameters,
            $clearUnknownParams
        ))->renderString($template);
    }

    public function renderString(string $template): string {
        // find all keys in the template
        $keys = $this->findVars($template);
        if ($this->clearUnknownParams) {
            // find keys missing from $this->keys
            $missingKeys = array_diff($keys, $this->parameterKeys);
            // remove missing key strings from the template
            $template = str_replace($missingKeys, '', $template);
        }
        // render values
        return str_replace($this->parameterKeys, $this->parameterValues, $template);
    }

//    public function renderString(string $template) : string {
//        return Prompt::twig()->from($template)->with($this->parameters)->toText();
//    }

    public function renderArray(
        array $rows,
        string $field = 'content'
    ): array {
        return array_map(
            fn($item) => $this->renderString($item[$field] ?? ''),
            $rows
        );
    }

    public function renderMessage(array|Message $message) : array {
        $normalized = match(true) {
            is_array($message) => Message::fromArray($message),
            $message instanceof Message => $message,
            default => throw new InvalidArgumentException('Invalid message type'),
        };

        // skip rendering if content is an array - it may contain non-text data
        if (is_array($normalized->content())) {
            return ['role' => $normalized->role()->value, 'content' => $normalized->content()];
        }

        return ['role' => $normalized->role()->value, 'content' => $this->renderString($normalized->content())];
    }

    public function renderMessages(array|Messages $messages) : array {
        return array_map(
            fn($message) => $this->renderMessage($message),
            is_array($messages) ? $messages : $messages->toArray()
        );
    }

    // OVERRIDEABLE //////////////////////////////////////////////////////////////

    protected function varPattern(string $key) : string {
        return '<|' . $key . '|>';
    }

    protected function findVars(string $template) : array {
        $matches = [];
        // replace {xxx} pattern with <|xxx|> pattern match
        preg_match_all('/<\|([^|]+)\|>/', $template, $matches);
        return $matches[0];
    }

    // INTERNAL //////////////////////////////////////////////////////////////////

    private function materializeParameters(array $parameters) : array {
        $parameterValues = [];
        foreach ($parameters as $key => $value) {
            $value = match (true) {
                is_scalar($value) => $value,
                is_array($value) => Json::encode($value),
                is_callable($value) => $value($key, $parameters),
                is_object($value) && method_exists($value, 'toString') => $value->toString(),
                is_object($value) && method_exists($value, 'toJson') => $value->toJson(),
                is_object($value) && method_exists($value, 'toArray') => Json::encode($value->toArray()),
                is_object($value) && method_exists($value, 'toSchema') => Json::encode($value->toSchema()),
                is_object($value) && method_exists($value, 'toOutputSchema') => Json::encode($value->toOutputSchema()),
                is_object($value) && property_exists($value, 'value') => $value->value(),
                is_object($value) => Json::encode($value),
                default => $value,
            };
            $parameterValues[$key] = $value;
        }
        return $parameterValues;
    }
}
