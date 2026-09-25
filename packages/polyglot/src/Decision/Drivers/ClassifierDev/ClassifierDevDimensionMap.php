<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Drivers\ClassifierDev;

use Cognesy\Polyglot\Decision\Data\DecisionRequest;
use Cognesy\Polyglot\Decision\Data\JsonContent;
use Cognesy\Polyglot\Decision\Exceptions\DecisionInvalidRequestException;
use Cognesy\Polyglot\Decision\Questions\Choice;
use Cognesy\Polyglot\Decision\Questions\Noul;
use Cognesy\Polyglot\Decision\Questions\Score;
use JsonException;
use stdClass;

final readonly class ClassifierDevDimensionMap
{
    private const int MAX_DIMENSIONS = 20;
    private const int MAX_LABELS = 100;
    private const int MAX_ITEM_LENGTH = 32_000;
    private const int MAX_LABEL_LENGTH = 200;
    private const int MAX_INSTRUCTION_LENGTH = 4_000;
    private const int MAX_DIMENSION_DEFINITIONS_LENGTH = 16_000;

    /**
     * @param array<string, array{
     *     question: Noul|Choice|Score,
     *     labels: list<string>,
     *     values: array<string, string|bool|int>,
     *     instructions: string
     * }> $entries
     */
    private function __construct(
        private string $item,
        private array $entries,
    ) {}

    public static function fromRequest(DecisionRequest $request): self {
        if ($request->questions()->count() > self::MAX_DIMENSIONS) {
            throw new DecisionInvalidRequestException('Classifier.dev supports at most 20 questions per request.');
        }

        $item = self::renderState($request->input());
        if ($item === '' || self::length($item) > self::MAX_ITEM_LENGTH) {
            throw new DecisionInvalidRequestException(
                'Classifier.dev state must contain between 1 and 32,000 characters.',
            );
        }

        $entries = [];
        foreach ($request->questions()->all() as $index => $question) {
            $wireName = "q{$index}";
            [$labels, $values] = self::labelsAndValues($question);
            $instructions = self::instructions($question);
            self::assertDimension($labels, $instructions);
            $entries[$wireName] = [
                'question' => $question,
                'labels' => $labels,
                'values' => $values,
                'instructions' => $instructions,
            ];
        }

        $map = new self($item, $entries);
        if (self::length(self::encode($map->dimensions())) > self::MAX_DIMENSION_DEFINITIONS_LENGTH) {
            throw new DecisionInvalidRequestException(
                'Classifier.dev dimension definitions must fit within 16,000 characters.',
            );
        }

        return $map;
    }

    public function item(): string {
        return $this->item;
    }

    public function dimensions(): stdClass {
        $dimensions = new stdClass();
        foreach ($this->entries as $wireName => $entry) {
            $dimension = new stdClass();
            $dimension->labels = $entry['labels'];
            $dimension->instructions = $entry['instructions'];
            $dimensions->{$wireName} = $dimension;
        }

        return $dimensions;
    }

    /** @return list<string> */
    public function wireNames(): array {
        return array_keys($this->entries);
    }

    /** @return list<string> */
    public function labels(string $wireName): array {
        return $this->entry($wireName)['labels'];
    }

    public function question(string $wireName): Noul|Choice|Score {
        return $this->entry($wireName)['question'];
    }

    public function value(string $wireName, string $wireLabel): string|bool|int {
        $values = $this->entry($wireName)['values'];

        return $values[self::key($wireLabel)]
            ?? throw new DecisionInvalidRequestException('Classifier.dev returned an unknown dimension label.');
    }

    /**
     * @return array{
     *     question: Noul|Choice|Score,
     *     labels: list<string>,
     *     values: array<string, string|bool|int>,
     *     instructions: string
     * }
     */
    private function entry(string $wireName): array {
        return $this->entries[$wireName]
            ?? throw new DecisionInvalidRequestException('Classifier.dev returned an unknown dimension.');
    }

    /** @return array{list<string>, array<string, string|bool|int>} */
    private static function labelsAndValues(Noul|Choice|Score $question): array {
        return match (true) {
            $question instanceof Choice => self::choiceLabels($question),
            $question instanceof Noul => self::noulLabels($question),
            $question instanceof Score => self::scoreLabels($question),
        };
    }

    /** @return array{list<string>, array<string, string|bool|int>} */
    private static function choiceLabels(Choice $question): array {
        if ($question->options()->count() < 2) {
            throw new DecisionInvalidRequestException(
                'Classifier.dev Choice questions require at least two options.',
            );
        }

        $labels = [];
        $values = [];
        foreach ($question->options()->all() as $option) {
            $description = $option->description();
            $label = match ($description) {
                null => $option->id(),
                default => $option->id() . ': ' . self::renderContent($description),
            };
            $labels[] = $label;
            $values[self::key($label)] = $option->id();
        }

        return [$labels, $values];
    }

    /** @return array{list<string>, array<string, string|bool|int>} */
    private static function noulLabels(Noul $question): array {
        $criteria = $question->criteria();
        $true = $criteria?->trueDescription();
        $false = $criteria?->falseDescription();
        $yes = match ($true) {
            null => 'yes',
            default => 'yes: ' . self::renderContent($true),
        };
        $no = match ($false) {
            null => 'no',
            default => 'no: ' . self::renderContent($false),
        };

        return [
            [$yes, $no],
            [self::key($yes) => true, self::key($no) => false],
        ];
    }

    /** @return array{list<string>, array<string, string|bool|int>} */
    private static function scoreLabels(Score $question): array {
        $labels = [];
        $values = [];
        foreach ($question->levels()->all() as $index => $level) {
            $label = "level {$index}: " . self::renderContent($level);
            $labels[] = $label;
            $values[self::key($label)] = $index;
        }

        return [$labels, $values];
    }

    private static function instructions(Noul|Choice|Score $question): string {
        $kind = match (true) {
            $question instanceof Choice => 'Choose exactly one label.',
            $question instanceof Noul => 'Choose yes when the criterion holds and no otherwise.',
            $question instanceof Score => 'Choose exactly one ordered level, from lowest to highest.',
        };
        $parts = [
            'Question ' . self::encode($question->id()) . '.',
            $kind,
            match ($question->instructions()) {
                null => '',
                default => self::renderContent($question->instructions()),
            },
        ];

        return implode(' ', array_values(array_filter(
            $parts,
            static fn (string $part): bool => $part !== '',
        )));
    }

    /** @param list<string> $labels */
    private static function assertDimension(array $labels, string $instructions): void {
        if (count($labels) < 2 || count($labels) > self::MAX_LABELS) {
            throw new DecisionInvalidRequestException(
                'Classifier.dev dimensions require between 2 and 100 labels.',
            );
        }
        if (count(array_unique($labels)) !== count($labels)) {
            throw new DecisionInvalidRequestException('Classifier.dev rendered dimension labels must be unique.');
        }
        foreach ($labels as $label) {
            if (trim($label) === '' || self::length($label) > self::MAX_LABEL_LENGTH) {
                throw new DecisionInvalidRequestException(
                    'Classifier.dev labels must contain between 1 and 200 characters.',
                );
            }
        }
        if (self::length($instructions) > self::MAX_INSTRUCTION_LENGTH) {
            throw new DecisionInvalidRequestException(
                'Classifier.dev dimension instructions must not exceed 4,000 characters.',
            );
        }
    }

    private static function renderState(JsonContent $state): string {
        if ($state->isText()) {
            $value = $state->value();

            return match (true) {
                is_string($value) => $value,
                default => throw new DecisionInvalidRequestException('Classifier.dev text state is invalid.'),
            };
        }

        return self::encode(self::canonicalize($state->value()));
    }

    private static function renderContent(JsonContent $content): string {
        if ($content->isText()) {
            $value = $content->value();

            return match (true) {
                is_string($value) => $value,
                default => throw new DecisionInvalidRequestException('Classifier.dev text content is invalid.'),
            };
        }

        return self::encode(self::canonicalize($content->value()));
    }

    private static function canonicalize(mixed $value): mixed {
        if ($value instanceof stdClass) {
            $properties = get_object_vars($value);
            ksort($properties, SORT_STRING);
            $object = new stdClass();
            foreach ($properties as $key => $item) {
                $object->{$key} = self::canonicalize($item);
            }

            return $object;
        }
        if (is_array($value)) {
            return array_map(self::canonicalize(...), $value);
        }

        return $value;
    }

    private static function encode(mixed $value): string {
        try {
            return json_encode(
                $value,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
            );
        } catch (JsonException) {
            throw new DecisionInvalidRequestException('Classifier.dev request content cannot be encoded.');
        }
    }

    private static function length(string $value): int {
        return intdiv(strlen(mb_convert_encoding($value, 'UTF-16LE', 'UTF-8')), 2);
    }

    private static function key(string $value): string {
        return '#' . $value;
    }
}
