<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use JsonException;
use Override;
use Symfony\Component\Yaml\Yaml;
use Traversable;

/** @implements IteratorAggregate<int, EvalDatasetRow> */
final readonly class EvalDataset implements Countable, IteratorAggregate
{
    /** @var list<EvalDatasetRow> */
    private array $rows;

    public function __construct(EvalDatasetRow ...$rows) {
        $this->rows = $rows;
    }

    /** @throws JsonException */
    public static function fromJson(string $path): self {
        $data = json_decode(self::read($path), true, flags: JSON_THROW_ON_ERROR);
        return self::fromData($data);
    }

    public static function fromYaml(string $path): self {
        return self::fromData(Yaml::parse(self::read($path)));
    }

    #[Override]
    public function count(): int {
        return count($this->rows);
    }

    #[Override]
    public function getIterator(): Traversable {
        yield from $this->rows;
    }

    private static function fromData(mixed $data): self {
        if (!is_array($data) || !array_is_list($data)) {
            throw new InvalidArgumentException('Eval dataset root must be a list of objects.');
        }
        $rows = [];
        foreach ($data as $row) {
            if (!is_array($row) || array_is_list($row)) {
                throw new InvalidArgumentException('Every eval dataset row must be an object.');
            }
            $rows[] = new EvalDatasetRow($row);
        }
        return new self(...$rows);
    }

    private static function read(string $path): string {
        $content = @file_get_contents($path);
        return is_string($content) ? $content : throw new InvalidArgumentException("Cannot read eval dataset: {$path}");
    }
}
