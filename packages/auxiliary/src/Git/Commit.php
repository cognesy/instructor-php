<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Git;

use DateTime;

class Commit
{
    protected GitService $gitService;
    protected string $hash;
    protected string $message;
    protected string $author;
    protected DateTime $date;
    protected string $diff;

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
        if (!isset($this->author)) {
            $author = $this->gitService->runCommand(sprintf('log -1 --pretty=format:%%an %%ae %s', escapeshellarg($this->hash)));
            $this->author = $author;
        }
        return $this->author;
    }

    public function message(): string {
        if (!isset($this->message)) {
            $message = $this->gitService->runCommand(sprintf('log -1 --pretty=format:%%B %s', escapeshellarg($this->hash)));
            $this->message = $message;
        }
        return $this->message;
    }

    public function date(): DateTime {
        if (!isset($this->date)) {
            $date = $this->gitService->runCommand(sprintf('log -1 --pretty=format:%%ad %s', escapeshellarg($this->hash)));
            $this->date = new DateTime($date);
        }
        return $this->date;
    }
}
