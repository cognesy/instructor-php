<?php

namespace Cognesy\Instructor\Utils;

use Cognesy\Instructor\Data\Messages\Message;
use Cognesy\Instructor\Data\Messages\Messages;
use InvalidArgumentException;

class Template
{
    private array $context = [];
    private array $contextValues = [];
    private array $contextKeys = [];
    private bool $clearUnknownParams = true;

    public function __construct(
        array $context = [],
        bool $clearUnknownParams = true,
    ) {
        $this->clearUnknownParams = $clearUnknownParams;
        $this->context = $context;
        if (empty($context)) {
            return;
        }
        // remove keys starting with @ - these are used for section templates
        $filteredContext = array_filter(
            $context,
            fn($key) => substr($key, 0, 1) !== '@',
            ARRAY_FILTER_USE_KEY
        );
        $materializedContext = $this->materializeContext($filteredContext);
        $this->contextValues = array_values($materializedContext);
        $this->contextKeys = array_map(
            fn($key) => $this->varPattern($key),
            array_keys($materializedContext)
        );
    }

    public function getContext() : array {
        return $this->context;
    }

    public static function cleanVarMarkers(string $template) : string {
        return str_replace(['<|', '|>'], '', $template);
    }

    public static function render(
        string $template,
        array $context,
        bool $clearUnknownParams = true,
    ) : string {
        return (new Template(
            $context,
            $clearUnknownParams
        ))->renderString($template);
    }

    public function renderString(string $template): string {
        // find all keys in the template
        $keys = $this->findVars($template);
        if ($this->clearUnknownParams) {
            // find keys missing from $this->keys
            $missingKeys = array_diff($keys, $this->contextKeys);
            // remove missing key strings from the template
            $template = str_replace($missingKeys, '', $template);
        }
        // render values
        return str_replace($this->contextKeys, $this->contextValues, $template);
    }

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
        if (is_array($normalized->content)) {
            return ['role' => $normalized->role, 'content' => $normalized->content];
        }

        return ['role' => $normalized->role, 'content' => $this->renderString($normalized->content)];
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

    private function materializeContext(array $context) : array {
        // TODO: is there a way to consolidate value rendering?
        $contextValues = [];
        foreach ($context as $key => $value) {
            $value = match (true) {
                is_scalar($value) => $value,
                is_array($value) => Json::encode($value),
                is_callable($value) => $value($key, $context),
                is_object($value) && method_exists($value, 'toString') => $value->toString(),
                is_object($value) && method_exists($value, 'toJson') => $value->toJson(),
                is_object($value) && method_exists($value, 'toArray') => Json::encode($value->toArray()),
                is_object($value) && method_exists($value, 'toSchema') => Json::encode($value->toSchema()),
                is_object($value) && method_exists($value, 'toOutputSchema') => Json::encode($value->toOutputSchema()),
                is_object($value) && property_exists($value, 'value') => $value->value(),
                is_object($value) => Json::encode($value),
                default => $value,
            };
            $contextValues[$key] = $value;
        }
        return $contextValues;
    }
}
