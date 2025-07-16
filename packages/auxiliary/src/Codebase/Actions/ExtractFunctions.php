<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Codebase\Actions;

use Cognesy\Auxiliary\Codebase\Codebase;
use Cognesy\Auxiliary\Codebase\Data\CodeFunction;
use Cognesy\Auxiliary\Codebase\NodeUtils;
use PhpParser\Node;
use PhpParser\NodeFinder;

class ExtractFunctions
{
    private Codebase $codebase;
    private NodeUtils $nodeUtils;

    public function __construct(Codebase $codebase) {
        $this->codebase = $codebase;
        $this->nodeUtils = new NodeUtils();
    }

    public function __invoke(array $ast): array {
        $nodeFinder = new NodeFinder();
        $functions = $nodeFinder->findInstanceOf($ast, Node\Stmt\Function_::class);

        $functionList = [];
        foreach ($functions as $function) {
            $functionName = $function->namespacedName->toString();
            $functionList[$functionName] = new CodeFunction(
                namespace: $this->codebase->getNamespace($function),
                name: $functionName,
                shortName: $function->name->toString(),
                docComment: $this->nodeUtils->getDocComment($function),
                body: $this->nodeUtils->getNodeCode($function),
            );
        }
        return $functionList;
    }
}