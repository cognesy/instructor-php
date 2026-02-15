<?php declare(strict_types=1);

namespace Cognesy\Agents\Tool\Tools;

use Cognesy\Agents\Tool\Contracts\CanDescribeTool;
use Cognesy\Agents\Tool\Contracts\ToolInterface;
use Cognesy\Agents\Tool\Traits\HasArgs;
use Cognesy\Agents\Tool\Traits\HasDescriptor;
use Cognesy\Agents\Tool\Traits\HasResultWrapper;

abstract class SimpleTool implements ToolInterface, CanDescribeTool
{
    use HasDescriptor;
    use HasResultWrapper;
    use HasArgs;

    public function __construct(CanDescribeTool $descriptor) {
        $this->initializeDescriptor($descriptor);
    }

    abstract public function __invoke(mixed ...$args): mixed;
}
