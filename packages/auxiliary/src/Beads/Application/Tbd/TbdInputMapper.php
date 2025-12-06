<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Application\Tbd;

use Cognesy\Auxiliary\Beads\FileFormat\Jsonl\Enums\IssueTypeEnum;
use Cognesy\Auxiliary\Beads\FileFormat\Jsonl\Enums\PriorityEnum;
use Cognesy\Auxiliary\Beads\FileFormat\Jsonl\Enums\StatusEnum;
use Cognesy\Auxiliary\Beads\FileFormat\Jsonl\Enums\DependencyTypeEnum;
use RuntimeException;

class TbdInputMapper
{
    public function status(?string $value): StatusEnum {
        if ($value === null || $value === '') {
            return StatusEnum::OPEN;
        }
        return StatusEnum::from(strtolower($value));
    }

    public function type(?string $value): IssueTypeEnum {
        if ($value === null || $value === '') {
            return IssueTypeEnum::TASK;
        }
        return IssueTypeEnum::from(strtolower($value));
    }

    public function priority(?string $value): PriorityEnum {
        if ($value === null || $value === '') {
            return PriorityEnum::MEDIUM;
        }
        $int = (int) $value;
        return PriorityEnum::from($int);
    }

    public function labels(?string $csv): array {
        if ($csv === null || trim($csv) === '') {
            return [];
        }
        $parts = array_map('trim', explode(',', $csv));
        $parts = array_filter($parts, fn($p) => $p !== '');
        return array_values(array_unique($parts));
    }

    public function dependencyType(?string $value): DependencyTypeEnum {
        if ($value === null || $value === '') {
            return DependencyTypeEnum::BLOCKS;
        }
        return DependencyTypeEnum::from(strtolower($value));
    }

    public function direction(?string $value): string {
        if ($value === null || $value === '') {
            return 'down';
        }
        $dir = strtolower($value);
        if (!in_array($dir, ['up', 'down', 'both'], true)) {
            throw new RuntimeException("Invalid direction: {$value}");
        }
        return $dir;
    }
}
