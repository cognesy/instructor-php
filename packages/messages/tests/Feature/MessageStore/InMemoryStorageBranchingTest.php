<?php declare(strict_types=1);

use Cognesy\Messages\MessageStore\Storage\InMemoryStorage;
use Cognesy\Messages\Tests\Support\StorageBranchingContract;

// Shared bodies, so a fix to one backend cannot drift from the other. The JsonlStorage
// registration of the same contract lives in tests/Integration/MessageStore/.
StorageBranchingContract::register('InMemoryStorage', fn() => new InMemoryStorage());
