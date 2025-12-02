<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\ClaudeCodeCli\Application\Parser;

use Cognesy\Auxiliary\ClaudeCodeCli\Application\Dto\ClaudeResponse;
use Cognesy\Auxiliary\ClaudeCodeCli\Domain\Collection\DecodedObjectCollection;
use Cognesy\Auxiliary\ClaudeCodeCli\Domain\Enum\OutputFormat;
use Cognesy\Auxiliary\ClaudeCodeCli\Domain\Value\DecodedObject;
use Cognesy\Utils\Sandbox\Data\ExecResult;

final class ResponseParser
{
    public function parse(ExecResult $result, OutputFormat $format) : ClaudeResponse {
        return match ($format) {
            OutputFormat::Json => $this->fromJson($result),
            OutputFormat::StreamJson => $this->fromStreamJson($result),
            OutputFormat::Text => new ClaudeResponse($result, DecodedObjectCollection::empty()),
        };
    }

    private function fromJson(ExecResult $result) : ClaudeResponse {
        $decoded = json_decode($result->stdout(), true);
        if (!is_array($decoded)) {
            return new ClaudeResponse($result, DecodedObjectCollection::empty());
        }
        $items = [];
        foreach ($this->normalizeToList($decoded) as $entry) {
            if (is_array($entry)) {
                $items[] = new DecodedObject($entry);
            }
        }
        return new ClaudeResponse($result, DecodedObjectCollection::of($items));
    }

    private function fromStreamJson(ExecResult $result) : ClaudeResponse {
        $lines = preg_split('/\r\n|\r|\n/', $result->stdout());
        if (!is_array($lines)) {
            return new ClaudeResponse($result, DecodedObjectCollection::empty());
        }
        $items = [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                $items[] = new DecodedObject($decoded);
            }
        }
        return new ClaudeResponse($result, DecodedObjectCollection::of($items));
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function normalizeToList(array $decoded) : array {
        if (array_is_list($decoded)) {
            /** @var list<array<string,mixed>> $decoded */
            return $decoded;
        }
        /** @var list<array<string,mixed>> $out */
        $out = [$decoded];
        return $out;
    }
}
