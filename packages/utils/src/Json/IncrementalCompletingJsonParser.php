<?php declare(strict_types=1);

namespace Cognesy\Utils\Json;

final class IncrementalCompletingJsonParser
{
    private string $buffer = '';
    /** @var list<array{type: string, state: string}> */
    private array $stack = [];
    private bool $rootStarted = false;
    private bool $rootComplete = false;

    private bool $inString = false;
    private bool $stringIsKey = false;
    private bool $escaping = false;
    private int $unicodeDigitsRemaining = 0;

    private string $activeToken = '';
    private string $tokenBuffer = '';
    private ?string $rootType = null;
    private int $revision = 0;
    private int $completionRevision = -1;
    private bool $completionAvailable = false;
    private string $cachedCompletionSuffix = '';
    private int $currentArrayRevision = -1;

    /** @var array<array-key, mixed>|null */
    private ?array $lastSuccessfulArray = null;
    /** @var array<array-key, mixed>|null */
    private ?array $cachedCurrentArray = null;

    public function reset(): void
    {
        $this->buffer = '';
        $this->stack = [];
        $this->rootStarted = false;
        $this->rootComplete = false;
        $this->inString = false;
        $this->stringIsKey = false;
        $this->escaping = false;
        $this->unicodeDigitsRemaining = 0;
        $this->activeToken = '';
        $this->tokenBuffer = '';
        $this->rootType = null;
        $this->lastSuccessfulArray = null;
        $this->revision = 0;
        $this->completionRevision = -1;
        $this->completionAvailable = false;
        $this->cachedCompletionSuffix = '';
        $this->currentArrayRevision = -1;
        $this->cachedCurrentArray = null;
    }

    public function append(string $chunk): void
    {
        if ($chunk === '') {
            return;
        }

        $this->buffer .= $chunk;
        $this->revision++;
        $this->invalidateCaches();

        foreach (str_split($chunk) as $char) {
            $this->consume($char);
        }
    }

    public function buffer(): string
    {
        return $this->buffer;
    }

    public function currentJson(): ?string
    {
        if (!$this->rootStarted) {
            return null;
        }

        $suffix = $this->completionSuffix();
        if ($suffix === null) {
            return null;
        }

        return $this->buffer . $suffix;
    }

    /**
     * @return array<array-key, mixed>|null
     */
    public function currentArray(): ?array
    {
        if ($this->currentArrayRevision === $this->revision) {
            return $this->cachedCurrentArray;
        }

        if (!$this->isDecodeEligible()) {
            return $this->cacheCurrentArray($this->lastSuccessfulArray);
        }

        $suffix = $this->completionSuffix();
        if ($suffix === null) {
            return $this->cacheCurrentArray($this->lastSuccessfulArray);
        }

        $json = match ($suffix) {
            '' => $this->buffer,
            default => $this->buffer . $suffix,
        };

        $decoded = json_decode($json, true, 512);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->cacheCurrentArray($this->lastSuccessfulArray);
        }

        if (!is_array($decoded)) {
            return $this->cacheCurrentArray($this->lastSuccessfulArray);
        }

        $this->lastSuccessfulArray = $decoded;
        return $this->cacheCurrentArray($decoded);
    }

    public function completionSuffix(): ?string
    {
        if (!$this->rootStarted) {
            return '';
        }

        if ($this->completionRevision === $this->revision) {
            return match ($this->completionAvailable) {
                true => $this->cachedCompletionSuffix,
                default => null,
            };
        }

        $suffix = '';
        $stack = $this->stack;
        $rootComplete = $this->rootComplete;

        if (!$this->completePendingToken($suffix, $stack, $rootComplete)) {
            $this->cacheCompletion(null);
            return null;
        }

        while ($stack !== []) {
            if (!$this->closeOpenContainer($suffix, $stack, $rootComplete)) {
                $this->cacheCompletion(null);
                return null;
            }
        }

        $this->cacheCompletion($suffix);
        return $suffix;
    }

    private function consume(string $char): void
    {
        if ($this->rootComplete) {
            return;
        }

        if ($this->inString) {
            $this->consumeInString($char);
            return;
        }

        if ($this->activeToken !== '') {
            $this->consumeInToken($char);
            return;
        }

        if (ctype_space($char)) {
            return;
        }

        match (true) {
            $char === '{' => $this->openContainer('object'),
            $char === '[' => $this->openContainer('array'),
            $char === '}' => $this->closeContainer('object'),
            $char === ']' => $this->closeContainer('array'),
            $char === ':' => $this->consumeColon(),
            $char === ',' => $this->consumeComma(),
            $char === '"' => $this->startString(),
            $this->isNumberStart($char) => $this->startToken('number', $char),
            ctype_alpha($char) => $this->startToken('literal', $char),
            default => null,
        };
    }

    private function consumeInString(string $char): void
    {
        if ($this->unicodeDigitsRemaining > 0) {
            $this->unicodeDigitsRemaining -= ctype_xdigit($char) ? 1 : $this->unicodeDigitsRemaining;
            return;
        }

        if ($this->escaping) {
            $this->escaping = false;
            $this->unicodeDigitsRemaining = $char === 'u' ? 4 : 0;
            return;
        }

        if ($char === '\\') {
            $this->escaping = true;
            return;
        }

        if ($char !== '"') {
            return;
        }

        $this->inString = false;

        if ($this->stringIsKey) {
            $this->setTopState('expect_colon');
            return;
        }

        $this->advanceAfterValue($this->stack, $this->rootComplete);
    }

    private function consumeInToken(string $char): void
    {
        $continues = match ($this->activeToken) {
            'number' => preg_match('/[0-9eE+\-\.]/', $char) === 1,
            'literal' => ctype_alpha($char),
            default => false,
        };

        if ($continues) {
            $this->tokenBuffer .= $char;
            return;
        }

        $this->finalizeActiveToken();
        $this->consume($char);
    }

    private function openContainer(string $type): void
    {
        if (!$this->rootStarted) {
            $this->rootType = $type;
        }

        $this->rootStarted = true;
        $this->stack[] = [
            'type' => $type,
            'state' => match ($type) {
                'object' => 'expect_first_key_or_end',
                default => 'expect_first_value_or_end',
            },
        ];
    }

    private function closeContainer(string $type): void
    {
        $index = array_key_last($this->stack);
        if ($index === null) {
            return;
        }

        if ($this->stack[$index]['type'] !== $type) {
            return;
        }

        array_pop($this->stack);
        $this->advanceAfterValue($this->stack, $this->rootComplete);
    }

    private function consumeColon(): void
    {
        if ($this->topState() !== 'expect_colon') {
            return;
        }

        $this->setTopState('expect_value');
    }

    private function consumeComma(): void
    {
        $state = $this->topState();
        if ($state === null) {
            return;
        }

        $next = match ($state) {
            'expect_comma_or_end' => match ($this->topType()) {
                'object' => 'expect_key',
                'array' => 'expect_value',
                default => null,
            },
            default => null,
        };

        if ($next === null) {
            return;
        }

        $this->setTopState($next);
    }

    private function startString(): void
    {
        if (!$this->rootStarted) {
            $this->rootType = 'scalar';
        }

        $this->rootStarted = true;
        $this->inString = true;
        $this->escaping = false;
        $this->unicodeDigitsRemaining = 0;
        $this->stringIsKey = $this->isKeyContext();
    }

    private function startToken(string $type, string $char): void
    {
        if (!$this->rootStarted) {
            $this->rootType = 'scalar';
        }

        $this->rootStarted = true;
        $this->activeToken = $type;
        $this->tokenBuffer = $char;
    }

    private function finalizeActiveToken(): void
    {
        if ($this->activeToken === '') {
            return;
        }

        $this->advanceAfterValue($this->stack, $this->rootComplete);
        $this->activeToken = '';
        $this->tokenBuffer = '';
    }

    /**
     * @param list<array{type: string, state: string}> $stack
     */
    private function advanceAfterValue(array &$stack, bool &$rootComplete): void
    {
        if ($stack === []) {
            $rootComplete = $this->rootStarted;
            return;
        }

        $index = array_key_last($stack);
        if ($index === null) {
            $rootComplete = $this->rootStarted;
            return;
        }

        $state = $stack[$index]['state'];
        $stack[$index]['state'] = match ($state) {
            'expect_value', 'expect_first_value_or_end' => 'expect_comma_or_end',
            default => $state,
        };
    }

    /**
     * @param list<array{type: string, state: string}> $stack
     */
    private function completePendingToken(string &$suffix, array &$stack, bool &$rootComplete): bool
    {
        if ($this->inString) {
            $suffix .= $this->stringCompletionPrefix();
            $suffix .= '"';

            if (!$this->stringIsKey) {
                $this->advanceAfterValue($stack, $rootComplete);
                return true;
            }

            if ($stack === []) {
                return false;
            }

            $suffix .= ':null';
            $this->setTopStateOn($stack, 'expect_comma_or_end');
            return true;
        }

        if ($this->activeToken === 'number') {
            $suffix .= $this->numberCompletionSuffix($this->tokenBuffer);
            $this->advanceAfterValue($stack, $rootComplete);
            return true;
        }

        if ($this->activeToken !== 'literal') {
            return true;
        }

        $completion = $this->literalCompletionSuffix($this->tokenBuffer);
        if ($completion === null) {
            return false;
        }

        $suffix .= $completion;
        $this->advanceAfterValue($stack, $rootComplete);
        return true;
    }

    /**
     * @param list<array{type: string, state: string}> $stack
     */
    private function closeOpenContainer(string &$suffix, array &$stack, bool &$rootComplete): bool
    {
        $index = array_key_last($stack);
        if ($index === null) {
            return true;
        }

        $frame = $stack[$index];

        return match ($frame['type']) {
            'object' => $this->closeOpenObject($suffix, $stack, $rootComplete, $frame['state']),
            'array' => $this->closeOpenArray($suffix, $stack, $rootComplete, $frame['state']),
            default => false,
        };
    }

    /**
     * @param list<array{type: string, state: string}> $stack
     */
    private function closeOpenObject(
        string &$suffix,
        array &$stack,
        bool &$rootComplete,
        string $state,
    ): bool {
        $prefix = match ($state) {
            'expect_first_key_or_end', 'expect_comma_or_end' => '',
            'expect_colon' => ':null',
            'expect_value' => 'null',
            'expect_key' => null,
            default => null,
        };

        if ($prefix === null) {
            return false;
        }

        $suffix .= $prefix . '}';
        array_pop($stack);
        $this->advanceAfterValue($stack, $rootComplete);
        return true;
    }

    /**
     * @param list<array{type: string, state: string}> $stack
     */
    private function closeOpenArray(
        string &$suffix,
        array &$stack,
        bool &$rootComplete,
        string $state,
    ): bool {
        $prefix = match ($state) {
            'expect_first_value_or_end', 'expect_comma_or_end' => '',
            'expect_value' => null,
            default => null,
        };

        if ($prefix === null) {
            return false;
        }

        $suffix .= $prefix . ']';
        array_pop($stack);
        $this->advanceAfterValue($stack, $rootComplete);
        return true;
    }

    private function stringCompletionPrefix(): string
    {
        $suffix = '';

        if ($this->escaping && $this->unicodeDigitsRemaining === 0) {
            $suffix .= '"';
        }

        if ($this->unicodeDigitsRemaining > 0) {
            $suffix .= str_repeat('0', $this->unicodeDigitsRemaining);
        }

        return $suffix;
    }

    private function numberCompletionSuffix(string $buffer): string
    {
        if ($buffer === '-' || str_ends_with($buffer, '.')) {
            return '0';
        }

        if (preg_match('/[eE][+\-]?$/', $buffer) === 1) {
            return '0';
        }

        return '';
    }

    private function literalCompletionSuffix(string $buffer): ?string
    {
        $lower = strtolower($buffer);

        return match (true) {
            str_starts_with('true', $lower) => substr('true', strlen($lower)),
            str_starts_with('false', $lower) => substr('false', strlen($lower)),
            str_starts_with('null', $lower) => substr('null', strlen($lower)),
            default => null,
        };
    }

    private function isKeyContext(): bool
    {
        return match ($this->topType()) {
            'object' => in_array($this->topState(), ['expect_first_key_or_end', 'expect_key'], true),
            default => false,
        };
    }

    private function isNumberStart(string $char): bool
    {
        return preg_match('/[0-9\-]/', $char) === 1;
    }

    private function topType(): ?string
    {
        $index = array_key_last($this->stack);
        return match ($index) {
            null => null,
            default => $this->stack[$index]['type'],
        };
    }

    private function topState(): ?string
    {
        $index = array_key_last($this->stack);
        return match ($index) {
            null => null,
            default => $this->stack[$index]['state'],
        };
    }

    private function setTopState(string $state): void
    {
        $index = array_key_last($this->stack);
        if ($index === null) {
            return;
        }

        $this->stack[$index]['state'] = $state;
    }

    /**
     * @param list<array{type: string, state: string}> $stack
     */
    private function setTopStateOn(array &$stack, string $state): void
    {
        $index = array_key_last($stack);
        if ($index === null) {
            return;
        }

        $stack[$index]['state'] = $state;
    }

    private function invalidateCaches(): void
    {
        $this->completionRevision = -1;
        $this->completionAvailable = false;
        $this->cachedCompletionSuffix = '';
        $this->currentArrayRevision = -1;
        $this->cachedCurrentArray = null;
    }

    private function cacheCompletion(?string $suffix): void
    {
        $this->completionRevision = $this->revision;
        $this->completionAvailable = $suffix !== null;
        $this->cachedCompletionSuffix = $suffix ?? '';
    }

    /**
     * @param array<array-key, mixed>|null $array
     * @return array<array-key, mixed>|null
     */
    private function cacheCurrentArray(?array $array): ?array
    {
        $this->currentArrayRevision = $this->revision;
        $this->cachedCurrentArray = $array;
        return $array;
    }

    private function isDecodeEligible(): bool
    {
        if (!$this->hasContainerRoot()) {
            return false;
        }

        if ($this->topState() === 'expect_key') {
            return false;
        }

        if ($this->topType() === 'array' && $this->topState() === 'expect_value' && !$this->hasPendingValueStart()) {
            return false;
        }

        if ($this->activeToken !== 'literal') {
            return true;
        }

        return $this->literalCompletionSuffix($this->tokenBuffer) !== null;
    }

    private function hasContainerRoot(): bool
    {
        return match ($this->rootType) {
            'object', 'array' => true,
            default => false,
        };
    }

    private function hasPendingValueStart(): bool
    {
        if ($this->inString) {
            return true;
        }

        if ($this->activeToken !== '') {
            return true;
        }

        return $this->topState() === 'expect_first_value_or_end';
    }
}
