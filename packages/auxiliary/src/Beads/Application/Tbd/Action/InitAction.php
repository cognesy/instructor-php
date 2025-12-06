<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Application\Tbd\Action;

use Cognesy\Auxiliary\Beads\Application\Tbd\TbdIssueStore;

class InitAction
{
    public function __construct(
        private readonly TbdIssueStore $store,
    ) {}

    public function __invoke(string $filePath): void {
        $this->store->ensureFileExists($filePath);
    }
}
