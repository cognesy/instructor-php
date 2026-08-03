<?php declare(strict_types=1);

namespace Cognesy\Agents\Profile;

use Cognesy\Agents\Hook\Enums\HookTrigger;

final readonly class AgentDescription
{
    public function __construct(private AgentProfile $profile) {}

    /** @return array<string, mixed> */
    public function toArray(): array {
        $metadata = $this->profile->metadata->toArray();
        $selfKnowledge = $metadata['selfKnowledge'] ?? null;
        unset($metadata['selfKnowledge'], $metadata['prompt_sections']);

        return [
            'name' => $this->profile->name(),
            'description' => $this->profile->description(),
            'driver' => $this->profile->driverClass,
            'llm' => $this->profile->llm?->toArray(),
            'capabilities' => $this->profile->capabilities->toArray(),
            'tools' => $this->tools(),
            'hooks' => $this->hooks(),
            'selfKnowledge' => is_array($selfKnowledge) ? $selfKnowledge : null,
            'metadata' => $metadata,
        ];
    }

    public function toText(): string {
        $description = $this->toArray();
        $lines = [
            "Agent: {$description['name']}",
            "Description: {$description['description']}",
            'Driver: ' . ($description['driver'] ?? 'none'),
            'LLM: ' . $this->llmText($description['llm']),
            'Capabilities:',
        ];
        foreach ($description['capabilities'] as $capability) {
            $lines[] = "- {$capability['name']} ({$capability['class']})";
        }
        if ($description['capabilities'] === []) {
            $lines[] = '- (none)';
        }
        $lines[] = 'Tools:';
        foreach ($description['tools'] as $tool) {
            $visibility = $tool['visibleInPrompt'] ? 'visible' : 'hidden';
            $lines[] = "- {$tool['name']} [{$visibility}]: {$tool['description']}";
        }
        if ($description['tools'] === []) {
            $lines[] = '- (none)';
        }
        $lines[] = 'Hooks:';
        foreach ($description['hooks'] as $hook) {
            $lines[] = "- {$hook['trigger']} {$hook['class']} priority={$hook['priority']}";
        }
        if ($description['hooks'] === []) {
            $lines[] = '- (none)';
        }
        $lines[] = 'Self-knowledge: ' . ($description['selfKnowledge'] === null ? 'none' : 'available');
        return implode("\n", $lines);
    }

    public function toMarkdown(): string {
        $description = $this->toArray();
        $lines = [
            "# {$description['name']}",
            '',
            (string) $description['description'],
            '',
            '## Runtime',
            '',
            '- Driver: `' . ($description['driver'] ?? 'none') . '`',
            '- LLM: ' . $this->llmText($description['llm']),
            '',
            '## Capabilities',
            '',
        ];
        foreach ($description['capabilities'] as $capability) {
            $lines[] = "- `{$capability['name']}` — `{$capability['class']}`";
        }
        if ($description['capabilities'] === []) {
            $lines[] = '- None';
        }
        $lines[] = '';
        $lines[] = '## Tools';
        $lines[] = '';
        foreach ($description['tools'] as $tool) {
            $visibility = $tool['visibleInPrompt'] ? 'visible in prompt' : 'not visible in prompt';
            $lines[] = "- `{$tool['name']}` ({$visibility}) — {$tool['description']}";
        }
        if ($description['tools'] === []) {
            $lines[] = '- None';
        }
        $lines[] = '';
        $lines[] = '## Hooks';
        $lines[] = '';
        foreach ($description['hooks'] as $hook) {
            $lines[] = "- `{$hook['trigger']}` — `{$hook['class']}` (priority {$hook['priority']})";
        }
        if ($description['hooks'] === []) {
            $lines[] = '- None';
        }
        return implode("\n", $lines);
    }

    /** @return list<array<string, mixed>> */
    private function tools(): array {
        return array_map(
            static fn (ToolProfile $tool): array => [
                'name' => $tool->name,
                'description' => $tool->description,
                'promptSnippet' => $tool->promptSnippet,
                'visibleInPrompt' => $tool->promptSnippet !== null,
                'deferred' => $tool->deferred,
            ],
            $this->profile->tools->all(),
        );
    }

    /** @return list<array<string, mixed>> */
    private function hooks(): array {
        $rows = [];
        foreach ($this->profile->hooks->all() as $registration => $hook) {
            foreach ($hook->triggers as $trigger) {
                $rows[] = [
                    'trigger' => $trigger,
                    'priority' => $hook->priority,
                    'class' => $hook->class,
                    'name' => $hook->name,
                    '_registration' => $registration,
                ];
            }
        }
        $order = array_flip(array_map(
            static fn (HookTrigger $trigger): string => $trigger->value,
            HookTrigger::cases(),
        ));
        usort($rows, static function (array $left, array $right) use ($order): int {
            $triggerOrder = ($order[$left['trigger']] ?? PHP_INT_MAX) <=> ($order[$right['trigger']] ?? PHP_INT_MAX);
            return match (true) {
                $triggerOrder !== 0 => $triggerOrder,
                $left['priority'] !== $right['priority'] => $right['priority'] <=> $left['priority'],
                default => $left['_registration'] <=> $right['_registration'],
            };
        });
        return array_map(static function (array $row): array {
            unset($row['_registration']);
            return $row;
        }, $rows);
    }

    private function llmText(mixed $llm): string {
        if (!is_array($llm)) {
            return 'none';
        }
        $driver = $llm['driver'] ?? 'unknown';
        $model = $llm['model'] ?? '';
        return $model !== '' ? "{$driver}/{$model}" : (string) $driver;
    }
}
