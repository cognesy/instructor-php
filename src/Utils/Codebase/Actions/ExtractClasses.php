<?php

namespace Cognesy\Instructor\Utils\Codebase\Actions;

use Cognesy\Instructor\Utils\Codebase\Codebase;
use PhpParser\Node;
use PhpParser\NodeFinder;

class ExtractClasses
{
    private Codebase $codebase;

    public function __construct(Codebase $codebase) {
        $this->codebase = $codebase;
    }

    public function __invoke(array $ast): array {
        $nodeFinder = new NodeFinder();
        $classes = $nodeFinder->findInstanceOf($ast, Node\Stmt\Class_::class);

        $classList = [];
        foreach ($classes as $class) {
            $classObject = (new MakeClass($this->codebase))($class);
            $classList[$classObject->name] = $classObject;
        }
        return $classList;
    }
}