<?php

namespace Cognesy\Instructor\Utils\Codebase;

use phpDocumentor\Reflection\DocBlockFactory;
use PhpParser\Node;
use PhpParser\PrettyPrinter\Standard;

class NodeUtils
{
    public function getNodeCode(Node $node): string {
        return (new Standard)->prettyPrint([$node]);
    }

    public function getDocComment(Node $node): string {
        if ($node->getDocComment()) {
            $docBlock = DocBlockFactory::createInstance()->create($node->getDocComment()->getText());
            return $docBlock->getSummary() . "\n" . $docBlock->getDescription()->render();
        }
        return '';
    }
}