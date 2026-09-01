<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Contract\Secrets;

use Cognesy\Tell\Data\TellCredentialStatus;
use SensitiveParameter;

interface CanManageTellCredentials
{
    /** @return list<string> */
    public function variables(): array;

    public function status(string $variable, string $directory): ?TellCredentialStatus;

    public function set(string $variable, #[SensitiveParameter] string $value): bool;

    public function remove(string $variable): bool;
}
