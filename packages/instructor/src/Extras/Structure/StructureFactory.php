<?php

namespace Cognesy\Instructor\Extras\Structure;

class StructureFactory
{
    use Traits\StructureFactory\CreatesStructureFromArray;
    use Traits\StructureFactory\CreatesStructureFromCallables;
    use Traits\StructureFactory\CreatesStructureFromClasses;
    use Traits\StructureFactory\CreatesStructureFromJsonSchema;
    use Traits\StructureFactory\CreatesStructureFromSchema;
    use Traits\StructureFactory\CreatesStructureFromString;
}