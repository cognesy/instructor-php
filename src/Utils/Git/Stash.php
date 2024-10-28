<?php

namespace Cognesy\Instructor\Utils\Git;

/**
 * Stash class provides methods to interact with Git stash functionality.
 * It allows for saving and applying stashed changes using a GitService.
 */
class Stash
{
    protected GitService $gitService;

    public function __construct(GitService $gitService) {
        $this->gitService = $gitService;
    }

    /**
     * Saves stash
     *
     * @return self
     */
    public function save(): self {
        $this->gitService->runCommand('stash');
        return $this;
    }

    /**
     * Applies the latest stashed changes to the working directory.
     *
     * @return self Returns the current instance for method chaining.
     */
    public function apply(): self {
        $this->gitService->runCommand('stash apply');
        return $this;
    }
}
