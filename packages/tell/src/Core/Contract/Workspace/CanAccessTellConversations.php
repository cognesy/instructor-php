<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Contract\Workspace;

/** Opens purpose-built canonical conversation facades without exposing stores. */
interface CanAccessTellConversations
{
    public function main(string $directory): CanUseTellConversation;

    public function conversation(string $directory, string $name): CanUseTellConversation;

    public function current(string $directory): CanUseTellBranch;

    public function branches(string $directory): CanManageTellBranches;

    public function branch(string $directory, string $name): CanUseTellBranch;

    public function ref(string $directory, string $hash): CanUseTellRef;

    public function configuration(string $directory, ?string $branch = null): CanConfigureTellBranch;

    public function sessions(string $directory): CanManageTellSessions;
}
