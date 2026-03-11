<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Skills;

final readonly class Skill
{
    /**
     * @param list<string> $resources
     * @param list<string> $allowedTools
     * @param array<string, string> $metadata
     */
    public function __construct(
        // Agent Skills Open Standard fields
        public string $name,
        public string $description,
        public string $body,
        public string $path,
        public ?string $license = null,
        public ?string $compatibility = null,
        public array $metadata = [],
        public array $allowedTools = [],
        // Cross-platform extension fields
        public bool $disableModelInvocation = false,
        public bool $userInvocable = true,
        public ?string $argumentHint = null,
        // Claude Code extension fields
        public ?string $model = null,
        public ?string $context = null,
        public ?string $agent = null,
        // Runtime
        public array $resources = [],
    ) {}

    public function toArray(): array {
        return array_filter([
            'name' => $this->name,
            'description' => $this->description,
            'body' => $this->body,
            'path' => $this->path,
            'license' => $this->license,
            'compatibility' => $this->compatibility,
            'metadata' => $this->metadata ?: null,
            'allowed-tools' => $this->allowedTools ?: null,
            'disable-model-invocation' => $this->disableModelInvocation ?: null,
            'user-invocable' => $this->userInvocable ? null : false,
            'argument-hint' => $this->argumentHint,
            'model' => $this->model,
            'context' => $this->context,
            'agent' => $this->agent,
            'resources' => $this->resources ?: null,
        ], fn($v) => $v !== null);
    }

    public function render(?string $arguments = null): string {
        $body = ($arguments !== null)
            ? self::substituteArguments($this->body, $arguments)
            : $this->body;

        $parts = [];
        $parts[] = "<skill name=\"{$this->name}\">";
        $parts[] = $body;

        if ($this->resources !== []) {
            $parts[] = "";
            $parts[] = "## Available Resources";
            foreach ($this->resources as $resource) {
                $parts[] = "- {$resource}";
            }
        }

        $parts[] = "</skill>";

        return implode("\n", $parts);
    }

    private static function substituteArguments(string $body, string $arguments): string {
        $args = preg_split('/\s+/', $arguments, -1, PREG_SPLIT_NO_EMPTY);
        $hasPlaceholder = false;

        // Replace $ARGUMENTS[N] (long form)
        $body = preg_replace_callback('/\$ARGUMENTS\[(\d+)]/', function (array $m) use ($args, &$hasPlaceholder) {
            $hasPlaceholder = true;
            return $args[(int) $m[1]] ?? '';
        }, $body);

        // Replace $N shorthand (only single digits to avoid false positives)
        // Must not be preceded by a word char (to avoid matching e.g. "var$0")
        $body = preg_replace_callback('/(?<!\w)\$(\d)(?!\d)/', function (array $m) use ($args, &$hasPlaceholder) {
            $hasPlaceholder = true;
            return $args[(int) $m[1]] ?? '';
        }, $body);

        // Replace $ARGUMENTS (full string) — do this last to avoid partial matches
        if (str_contains($body, '$ARGUMENTS')) {
            $hasPlaceholder = true;
            $body = str_replace('$ARGUMENTS', $arguments, $body);
        }

        // If no placeholder was found, append arguments
        if (!$hasPlaceholder && trim($arguments) !== '') {
            $body .= "\n\nARGUMENTS: {$arguments}";
        }

        return $body;
    }

    public function renderMetadata(): string {
        $hint = ($this->argumentHint !== null) ? " {$this->argumentHint}" : '';
        return "[{$this->name}{$hint}]: {$this->description}";
    }
}
