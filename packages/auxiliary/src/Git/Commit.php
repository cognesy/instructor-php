<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Git;

use DateTime;

class Commit
{
    protected GitService $gitService;
    protected string $hash;
    protected ?string $message = null;
    protected ?string $author = null;
    protected ?DateTime $date = null;
    protected ?string $diff = null;

    public function __construct(
        string $hash,
        GitService $gitService
    ) {
        $this->gitService = $gitService;
        $this->hash = $hash;
    }

    public function hash(): string {
        return $this->hash;
    }

    public function author(): string {
        if ($this->author === null) {
            $author = $this->gitService->runCommand(sprintf('log -1 --pretty=format:%%an %%ae %s', escapeshellarg($this->hash)));
            $this->author = $author;
        }
        return $this->author;
    }

    public function message(): string {
        if ($this->message === null) {
            $message = $this->gitService->runCommand(sprintf('log -1 --pretty=format:%%B %s', escapeshellarg($this->hash)));
            $this->message = $message;
        }
        return $this->message;
    }

    public function date(): DateTime {
        if ($this->date === null) {
            $date = $this->gitService->runCommand(sprintf('log -1 --pretty=format:%%ad %s', escapeshellarg($this->hash)));
            $this->date = new DateTime($date);
        }
        return $this->date;
    }
}
