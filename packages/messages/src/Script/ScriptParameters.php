<?php declare(strict_types=1);

namespace Cognesy\Messages\Script;

use Cognesy\Messages\Script\Traits\ScriptParameters\HandlesAccess;
use Cognesy\Messages\Script\Traits\ScriptParameters\HandlesConversion;
use Cognesy\Messages\Script\Traits\ScriptParameters\HandlesMutation;
use Cognesy\Messages\Script\Traits\ScriptParameters\HandlesTransformation;

final readonly class ScriptParameters
{
    use HandlesAccess;
    use HandlesConversion;
    use HandlesMutation;
    use HandlesTransformation;

    public function __construct(
        private array $parameters = [],
    ) {}
}
