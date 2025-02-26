<?php

namespace Cognesy\Auxiliary\Codebase\Actions;

use Cognesy\Auxiliary\Codebase\Codebase;
use Cognesy\Auxiliary\Codebase\Data\CodeClass;
use Cognesy\Auxiliary\Codebase\Data\CodeFunction;
use Cognesy\Auxiliary\Codebase\NodeUtils;
use PhpParser\Node;
use PhpParser\NodeFinder;

class MakeClass
{
    private Codebase $codebase;
    private NodeUtils $nodeUtils;

    public function __construct(Codebase $codebase) {
        $this->codebase = $codebase;
        $this->nodeUtils = new NodeUtils();
    }

    public function __invoke(Node\Stmt\Class_ $class) : CodeClass {
        $className = $class->namespacedName->toString();
        return new CodeClass(
            namespace: $this->codebase->getNamespace($class),
            name: $className,
            shortName: $class->name->toString(),
            extends: $this->getExtends($class),
            implements: $this->getImplements($class),
            uses: $this->getUses($class),
            imports: $this->getImports($class),
            methods: $this->getMethods($class),
            properties: $this->getProperties($class),
            docComment: $this->nodeUtils->getDocComment($class),
            body: $this->nodeUtils->getNodeCode($class),
        );
    }

    public function getImports(Node\Stmt\Class_ $class): array {
        $imports = [];
        $nodeFinder = new NodeFinder();
        $uses = $nodeFinder->findInstanceOf($class, Node\Stmt\Use_::class);

        foreach ($uses as $use) {
            foreach ($use->uses as $useUse) {
                $imports[] = $useUse->name->toString();
            }
        }

        return $imports;
    }

    public function getMethods(Node\Stmt\Class_ $class): array {
        $methods = [];
        foreach ($class->getMethods() as $method) {
            if ($method->isPublic()) {
                $methods[$method->name->toString()] = new CodeFunction(
                    namespace: $this->codebase->getNamespace($class),
                    name: $method->name->toString(),
                    shortName: $method->name->toString(),
                    docComment: $this->nodeUtils->getDocComment($method),
                    body: $this->nodeUtils->getNodeCode($method),
                );
            }
        }
        return $methods;
    }

    public function getProperties(Node\Stmt\Class_ $class): array {
        $properties = [];
        foreach ($class->getProperties() as $property) {
            if ($property->isPublic()) {
                $properties[] = $property->props[0]->name->toString();
            }
        }
        return $properties;
    }

    public function getExtends(Node\Stmt\Class_ $class): string {
        return $class->extends ? $class->extends->toString() : '';
    }

    public function getImplements(Node\Stmt\Class_ $class): array {
        $implements = [];
        foreach ($class->implements as $implement) {
            $implements[] = $implement->toString();
        }
        return $implements;
    }

    public function getUses(Node\Stmt\Class_ $class): array {
        $uses = [];
        $nodeFinder = new NodeFinder();
        $usesStmts = $nodeFinder->findInstanceOf($class, Node\Stmt\Use_::class);
        foreach ($usesStmts as $useStmt) {
            foreach ($useStmt->uses as $use) {
                $uses[] = $use->name->toString();
            }
        }
        return $uses;
    }
}