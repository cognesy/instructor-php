<?php
declare(strict_types=1);

namespace Cognesy\Auxiliary\AstGrep;

use Cognesy\Auxiliary\AstGrep\Data\SearchResults;
use Cognesy\Auxiliary\AstGrep\Enums\Language;

class UsageFinder
{
    private AstGrep $astGrep;
    private PatternBuilder $patternBuilder;

    public function __construct(
        ?string $workingDirectory = null,
        Language $language = Language::PHP,
    ) {
        $this->astGrep = new AstGrep($language, $workingDirectory);
        $this->patternBuilder = new PatternBuilder();
    }

    public function findClassUsages(string $className, string $path = '.'): ClassUsages {
        return new ClassUsages(
            className: $className,
            instantiations: $this->findClassInstantiations($className, $path),
            staticCalls: $this->findStaticMethodCalls($className, $path),
            extensions: $this->findClassExtensions($className, $path),
            implementations: $this->findClassImplementations($className, $path),
            imports: $this->findClassImports($className, $path),
        );
    }

    public function findMethodUsages(string $className, string $methodName, string $path = '.'): MethodUsages {
        return new MethodUsages(
            className: $className,
            methodName: $methodName,
            instanceCalls: $this->findInstanceMethodCalls($methodName, $path),
            staticCalls: $this->findStaticMethodCalls($className, $methodName, $path),
        );
    }

    public function findClassInstantiations(string $className, string $path = '.'): SearchResults {
        $pattern = $this->patternBuilder->classInstantiation($className)->build();
        return $this->astGrep->search($pattern, $path);
    }

    public function findStaticMethodCalls(string $className, ?string $methodName = null, string $path = '.'): SearchResults {
        if ($methodName === null) {
            $pattern = sprintf('%s::$METHOD($$$)', $className);
        } else {
            $pattern = $this->patternBuilder->staticMethodCall($className, $methodName)->build();
        }
        return $this->astGrep->search($pattern, $path);
    }

    public function findInstanceMethodCalls(string $methodName, string $path = '.'): SearchResults {
        $pattern = $this->patternBuilder->methodCall($methodName)->build();
        return $this->astGrep->search($pattern, $path);
    }

    public function findClassExtensions(string $className, string $path = '.'): SearchResults {
        $pattern = $this->patternBuilder->classExtends($className)->build();
        return $this->astGrep->search($pattern, $path);
    }

    public function findClassImplementations(string $interfaceName, string $path = '.'): SearchResults {
        $pattern = $this->patternBuilder->classImplements($interfaceName)->build();
        return $this->astGrep->search($pattern, $path);
    }

    public function findClassImports(string $className, string $path = '.'): SearchResults {
        $pattern = $this->patternBuilder->useStatement($className)->build();
        return $this->astGrep->search($pattern, $path);
    }

    public function findTraitUsages(string $traitName, string $path = '.'): SearchResults {
        $pattern = $this->patternBuilder->traitUse($traitName)->build();
        return $this->astGrep->search($pattern, $path);
    }

    public function findPropertyAccess(string $propertyName, string $path = '.'): SearchResults {
        $pattern = $this->patternBuilder->propertyAccess($propertyName)->build();
        return $this->astGrep->search($pattern, $path);
    }

    public function findFunctionCalls(string $functionName, string $path = '.'): SearchResults {
        $pattern = $this->patternBuilder->functionCall($functionName)->build();
        return $this->astGrep->search($pattern, $path);
    }

    public function isClassUsed(string $className, string $path = '.'): bool {
        $usages = $this->findClassUsages($className, $path);
        return $usages->hasAnyUsage();
    }

    public function isMethodUsed(string $className, string $methodName, string $path = '.'): bool {
        $usages = $this->findMethodUsages($className, $methodName, $path);
        return $usages->hasAnyUsage();
    }
}

readonly class ClassUsages
{
    public function __construct(
        public string $className,
        public SearchResults $instantiations,
        public SearchResults $staticCalls,
        public SearchResults $extensions,
        public SearchResults $implementations,
        public SearchResults $imports,
    ) {}

    public function hasAnyUsage(): bool {
        return $this->instantiations->isNotEmpty()
            || $this->staticCalls->isNotEmpty()
            || $this->extensions->isNotEmpty()
            || $this->implementations->isNotEmpty()
            || $this->imports->isNotEmpty();
    }

    public function getTotalUsageCount(): int {
        return $this->instantiations->count()
            + $this->staticCalls->count()
            + $this->extensions->count()
            + $this->implementations->count()
            + $this->imports->count();
    }

    public function getAllResults(): SearchResults {
        $all = new SearchResults();

        foreach ($this->instantiations as $result) {
            $all->add($result);
        }
        foreach ($this->staticCalls as $result) {
            $all->add($result);
        }
        foreach ($this->extensions as $result) {
            $all->add($result);
        }
        foreach ($this->implementations as $result) {
            $all->add($result);
        }
        foreach ($this->imports as $result) {
            $all->add($result);
        }

        return $all;
    }
}

readonly class MethodUsages
{
    public function __construct(
        public string $className,
        public string $methodName,
        public SearchResults $instanceCalls,
        public SearchResults $staticCalls,
    ) {}

    public function hasAnyUsage(): bool {
        return $this->instanceCalls->isNotEmpty() || $this->staticCalls->isNotEmpty();
    }

    public function getTotalUsageCount(): int {
        return $this->instanceCalls->count() + $this->staticCalls->count();
    }

    public function getAllResults(): SearchResults {
        $all = new SearchResults();

        foreach ($this->instanceCalls as $result) {
            $all->add($result);
        }
        foreach ($this->staticCalls as $result) {
            $all->add($result);
        }

        return $all;
    }
}