<?php declare(strict_types=1);

namespace Cognesy\Template\Script;

use Cognesy\Template\Script\Traits\ScriptParameters\HandlesAccess;
use Cognesy\Template\Script\Traits\ScriptParameters\HandlesConversion;
use Cognesy\Template\Script\Traits\ScriptParameters\HandlesMutation;
use Cognesy\Template\Script\Traits\ScriptParameters\HandlesTransformation;

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
