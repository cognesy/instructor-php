<?php

declare(strict_types=1);

namespace Cognesy\Tell\Adapter\Console\Symfony;

use Cognesy\Tell\Data\TellAnswers;
use InvalidArgumentException;
use JsonException;
use Symfony\Component\Console\Input\InputInterface;

/** Maps Symfony Console answer sources to a pure immutable Tell value. */
final readonly class TellAnswersInput
{
    public static function read(InputInterface $input): TellAnswers {
        /** @var list<string> $cli */
        $cli = $input->getOption('answer');
        $file = $input->getOption('answers-file');
        $stdin = (bool) $input->getOption('answers-stdin');
        if (!is_array($cli)) {
            throw new InvalidArgumentException('--answer must be repeatable string input.');
        }
        if ($cli !== [] && ((is_string($file) && $file !== '') || $stdin)) {
            throw new InvalidArgumentException('Use either repeatable --answer or exactly one structured answer source.');
        }
        if ((is_string($file) && $file !== '') && $stdin) {
            throw new InvalidArgumentException('Use either --answers-file or --answers-stdin, not both.');
        }
        if ($cli !== []) {
            return new TellAnswers(array_map(
                static fn (string $value): array => ['id' => null, 'value' => $value, 'source' => 'cli'],
                $cli,
            ));
        }
        if (!$stdin && (!is_string($file) || $file === '')) {
            return new TellAnswers();
        }
        $raw = match (true) {
            $stdin => stream_get_contents(STDIN, 262_145),
            default => is_file($file) ? file_get_contents($file) : false,
        };
        if (!is_string($raw) || strlen($raw) > 262_144 || preg_match('//u', $raw) !== 1) {
            throw new InvalidArgumentException('Structured answers must be readable UTF-8 JSON no larger than 262144 bytes.');
        }
        try {
            $items = json_decode($raw, true, 128, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidArgumentException('Structured answers must be a valid JSON array.');
        }
        if (!is_array($items) || !array_is_list($items)) {
            throw new InvalidArgumentException('Structured answers must be a JSON array.');
        }
        $source = $stdin ? 'stdin' : 'file';
        $entries = [];
        foreach ($items as $item) {
            $entry = is_string($item) ? ['id' => null, 'value' => $item] : $item;
            if (!is_array($entry) || !array_key_exists('value', $entry) || !is_string($entry['value'])
                || (isset($entry['id']) && !is_string($entry['id']))) {
                throw new InvalidArgumentException('Each structured answer must be a string or an object with string value and optional id.');
            }
            $entries[] = ['id' => $entry['id'] ?? null, 'value' => $entry['value'], 'source' => $source];
        }

        return new TellAnswers($entries);
    }
}
