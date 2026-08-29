<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace;

/** Canonical paths for one Tell workspace. */
final readonly class WorkspacePaths
{
    public const int SCHEMA_VERSION = 1;

    public string $marker;
    public string $arena;
    public string $objects;
    public string $refs;
    public string $locks;
    public string $config;
    public string $schema;
    public string $mainRef;
    public string $currentBranch;

    public function __construct(public string $root) {
        $this->marker = $this->join($root, '.tell');
        $this->arena = $this->join($this->marker, 'arena');
        $this->objects = $this->join($this->arena, 'objects');
        $this->refs = $this->join($this->arena, 'refs');
        $this->locks = $this->join($this->arena, 'locks');
        $this->config = $this->join($this->arena, 'config');
        $this->schema = $this->join($this->arena, 'schema');
        $this->mainRef = $this->join($this->refs, 'main');
        $this->currentBranch = $this->join($this->refs, 'current');
    }

    private function join(string $directory, string $name): string {
        $base = rtrim($directory, '/\\');
        if ($base === '') {
            $base = DIRECTORY_SEPARATOR;
        }

        return $base . DIRECTORY_SEPARATOR . $name;
    }
}
