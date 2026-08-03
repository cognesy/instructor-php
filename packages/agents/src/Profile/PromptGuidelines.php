<?php declare(strict_types=1);

namespace Cognesy\Agents\Profile;

final readonly class PromptGuidelines
{
    private const BASE = [
        'Be concise in your responses',
        'Show file paths clearly when working with files',
        'Report checks honestly; never claim a command passed unless you ran it',
    ];

    /**
     * @param list<string> $extra
     * @return list<string>
     */
    public static function collect(ToolProfileList $tools, array $extra = []): array {
        $collected = [];
        foreach ($tools->all() as $tool) {
            $collected = [...$collected, ...$tool->promptGuidelines];
        }
        $collected = [...$collected, ...$extra, ...self::conditional($tools), ...self::BASE];

        $unique = [];
        foreach ($collected as $guideline) {
            $trimmed = trim($guideline);
            if ($trimmed === '' || in_array($trimmed, $unique, true)) {
                continue;
            }
            $unique[] = $trimmed;
        }
        return $unique;
    }

    /** @return list<string> */
    private static function conditional(ToolProfileList $tools): array {
        if (!$tools->has('bash')) {
            return [];
        }

        $hasExplorationTool = $tools->has('grep') || $tools->has('find') || $tools->has('ls');
        return match ($hasExplorationTool) {
            true => ['Prefer the dedicated file tools over bash for exploration'],
            false => ['Use bash for file operations like ls, rg, find'],
        };
    }
}
