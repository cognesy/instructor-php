<?php

namespace Cognesy\Instructor\Extras\Structure;

class StructureFactory
{
    use Traits\Factory\CreatesStructureFromArray;
    use Traits\Factory\CreatesStructureFromString;
    use Traits\Factory\CreatesStructureFromCallables;
    use Traits\Factory\CreatesStructureFromClasses;
    use Traits\Factory\CreatesStructureFromJsonSchema;
}