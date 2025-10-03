<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Codebase;

use Cognesy\Auxiliary\Codebase\Actions\ExtractClasses;
use Cognesy\Auxiliary\Codebase\Actions\ExtractFunctions;
use Cognesy\Auxiliary\Codebase\Actions\ExtractNamespaces;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PhpParser\PhpVersion;
use Symfony\Component\Finder\Finder;

class Codebase
{
    private array $psr4Paths;
    private array $filesPaths;
    private array $phpFiles = [];
    private array $classes = [];
    private array $functions = [];
    private array $namespaces = [];

    public function __construct(string $projectPath) {
        $composerJson = new ComposerJson($projectPath);
        //$this->projectRoot = $composerJson->findSrcRoot($projectPath);
        $this->psr4Paths = $composerJson->getPsr4Paths();
        $this->filesPaths = $composerJson->getFilesPaths();
        // merge all paths
        $paths = array_merge($this->psr4Paths, $this->filesPaths);
        // find all php files in the paths
        $files = [];
        foreach ($paths as $path) {
            $files[$path] = $this->findPhpFiles($path);
        }
        $this->phpFiles = array_merge(...array_values($files));
        $this->analyzeProject($this->phpFiles);
    }

    public function getNamespace(mixed $class) : string {
        return $class->namespacedName->slice(0, -1)->toString();
    }

    public function getClasses(): array {
        return $this->classes;
    }

    public function getFunctions(): array {
        return $this->functions;
    }

    public function getNamespaces(): array {
        return $this->namespaces;
    }

    public function getParameters(Node\Stmt\ClassMethod $method) : array {
        if (!$method->getParams()) {
            return [];
        }
        $params = [];
        foreach ($method->getParams() as $param) {
            if ($param->var instanceof Node\Expr\Variable) {
                $params[] = $param->var->name;
            }
        }
        return $params;
    }

    public function getReturnType(Node\Stmt\ClassMethod $method) : string {
        return $method->returnType ? $method->returnType->toString() : '';
    }

    // INTERNAL /////////////////////////////////////////////////

    private function analyzeProject(array $phpFiles): void {
        $parser = (new ParserFactory)->createForVersion(PhpVersion::fromString('8.1'));
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());

        foreach ($phpFiles as $file) {
            $code = file_get_contents($file->getRealPath());
            if ($code === false) {
                continue;
            }
            $ast = $parser->parse($code);
            if ($ast === null) {
                continue;
            }
            $ast = $traverser->traverse($ast);

            $this->appendClasses((new ExtractClasses($this))($ast));
            $this->appendFunctions((new ExtractFunctions($this))($ast));
        }

        $this->namespaces = (new ExtractNamespaces)($this->classes, $this->functions);
    }

    private function findPhpFiles(string $path): array {
        $finder = new Finder();
        $finder->files()->in($path)->name('*.php');
        return iterator_to_array($finder, false);
    }

    private function appendClasses(array $classes): void {
        $this->classes = array_merge($this->classes, $classes);
    }

    private function appendFunctions(array $functions): void {
        $this->functions = array_merge($this->functions, $functions);
    }
}
