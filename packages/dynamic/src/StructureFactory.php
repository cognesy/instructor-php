<?php declare(strict_types=1);

namespace Cognesy\Dynamic;

class StructureFactory
{
    use Traits\StructureFactory\CreatesStructureFromArray;
    use Traits\StructureFactory\CreatesStructureFromCallables;
    use Traits\StructureFactory\CreatesStructureFromClasses;
    use Traits\StructureFactory\CreatesStructureFromJsonSchema;
    use Traits\StructureFactory\CreatesStructureFromSchema;
    use Traits\StructureFactory\CreatesStructureFromString;
}