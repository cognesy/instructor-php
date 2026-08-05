<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final readonly class EvalDiscovery
{
    private function __construct(private string $root) {}

    public static function in(string $root): self {
        return new self(rtrim($root, '/'));
    }

    public function discover(): AgentEvals {
        if (!is_dir($this->root)) {
            throw new InvalidArgumentException("Eval directory does not exist: {$this->root}");
        }
        $paths = $this->paths();
        $found = AgentEvals::none();
        $ids = [];
        foreach ($paths as $path) {
            $baseId = $this->idFor($path);
            $export = require $path;
            $evals = $this->normalize($export, $path);
            $many = $evals->count() !== 1 || $export instanceof AgentEvalSet || is_array($export);
            foreach ($evals as $index => $eval) {
                $id = $many ? sprintf('%s/%04d', $baseId, $index) : $baseId;
                if (isset($ids[$id])) {
                    throw new InvalidArgumentException("Duplicate eval id: {$id}");
                }
                $ids[$id] = true;
                $found = $found->with($eval->withId($id));
            }
        }
        return $found;
    }

    /** @return list<string> */
    private function paths(): array {
        $paths = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root));
        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && str_ends_with($file->getFilename(), '.eval.php')) {
                $paths[] = $file->getPathname();
            }
        }
        sort($paths);
        return $paths;
    }

    private function idFor(string $path): string {
        $relative = substr($path, strlen($this->root) + 1);
        return str_replace('\\', '/', substr($relative, 0, -strlen('.eval.php')));
    }

    private function normalize(mixed $export, string $path): AgentEvals {
        return match (true) {
            $export instanceof AgentEval => new AgentEvals($export),
            $export instanceof AgentEvalSet => $export->evals(),
            is_array($export) => $this->fromArray($export, $path),
            default => throw new InvalidArgumentException("Eval file must return AgentEval or AgentEvalSet: {$path}"),
        };
    }

    private function fromArray(array $exports, string $path): AgentEvals {
        foreach ($exports as $export) {
            if (!$export instanceof AgentEval) {
                throw new InvalidArgumentException("Eval array contains invalid value: {$path}");
            }
        }
        return new AgentEvals(...array_values($exports));
    }
}
