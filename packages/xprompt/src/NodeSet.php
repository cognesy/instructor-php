<?php

declare(strict_types=1);

namespace Cognesy\Xprompt;

use Symfony\Component\Yaml\Yaml;

class NodeSet extends Prompt
{
    public string $dataFile = '';
    public string $sortKey = '';
    /** @var list<array<string, mixed>> */
    public array $items = [];

    /**
     * Load, sort, and return items. Override for dynamic data.
     *
     * @return list<array<string, mixed>>
     */
    public function nodes(mixed ...$ctx): array
    {
        $items = $this->items;

        if ($this->dataFile !== '' && $items === []) {
            $items = $this->loadDataFile();
        }

        if ($this->sortKey !== '' && $items !== []) {
            usort($items, fn(array $a, array $b) =>
                ($a[$this->sortKey] ?? 0) <=> ($b[$this->sortKey] ?? 0)
            );
        }

        return $items;
    }

    /**
     * Format a single item. Override for custom rendering.
     */
    public function renderNode(int $index, array $node, mixed ...$ctx): string
    {
        $label = $node['label'] ?? $node['id'] ?? '';
        $content = $node['content'] ?? '';

        $line = $content !== ''
            ? "{$index}. **{$label}** -- {$content}"
            : "{$index}. **{$label}**";

        $children = $node['children'] ?? [];
        if ($children !== []) {
            $childLines = array_map(
                fn(array $c): string => '   - ' . ($c['content'] ?? $c['label'] ?? $c['id'] ?? ''),
                $children,
            );
            $line .= "\n" . implode("\n", $childLines);
        }

        return $line;
    }

    public function body(mixed ...$ctx): array
    {
        $items = $this->nodes(...$ctx);
        if ($items === []) {
            return [];
        }
        return array_map(
            fn(int $i, array $node): string => $this->renderNode($i, $node, ...$ctx),
            range(1, count($items)),
            $items,
        );
    }

    // -- Private --------------------------------------------------------

    private function loadDataFile(): array
    {
        $resourcePath = $this->resolveConfig()->resourcePath;
        $path = $resourcePath !== '' ? rtrim($resourcePath, '/') . '/' . $this->dataFile : $this->dataFile;

        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException("Data file not found: {$path}");
        }

        if (!class_exists(Yaml::class)) {
            throw new \RuntimeException('symfony/yaml is required for YAML data files. Install it via: composer require symfony/yaml');
        }

        $data = Yaml::parse($content);
        if (!is_array($data) || !array_is_list($data)) {
            throw new \RuntimeException("Data file must contain a YAML list: {$path}");
        }

        return $data;
    }
}
