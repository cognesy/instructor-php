# Key Reference Files

## Monorepo Root

- **CONTRIBUTOR_GUIDE.md** - Development workflows, package management, and contribution guidelines
- **CONTENTS.md** - Project structure and package overview
- **README.md** - Main project documentation

## Individual Subpackages

Each package in `packages/` may contain:
- **README.md** - Package-specific documentation
- **OVERVIEW.md** - Package overview and architecture
- **INTERNALS.md** - Implementation details and internal structure
- **CHEATSHEET.md** - Quick reference for package usage

# Code Style

- Use strict types and type hints for arguments and return values everywhere
- Do not nest control structures (if, loops) beyond 1 level, exceptionally 2 levels
- Use `match` for complex conditionals, avoid `if`/`else if` chains or `switch` statements
- Prefer immutable data structures and functional programming paradigms
- Avoid using arrays as collections - use dedicated collection classes
- Avoid exceptions for control flow - do not wrap everything in try/catch

# Design Principles

- Always start with extremely simple code, refactor when it is required - do not over-engineer solutions (YAGNI)
- Use DDD (Domain Driven Design) principles - aggregate roots, value objects, entities, repositories, services
- Use Clean Code and SOLID principles
- Use interfaces for contracts, avoid concrete class dependencies
- Early returns on errors, use monadic Cognesy\Utils\Result\Result for any complex error handling

# Tests and Quality

- Use Pest for testing
- Test only services and domain logic, not external dependencies or displayed information
- Before writing tests make sure that the code is easily testable - if not propose refactorings
- Execute tests from the monorepo root to ensure all dependencies are correctly resolved
- Use PHPStan and Psalm for static analysis

# Development Tools

- **ast-grep** - Structural code search and refactoring tool for PHP syntax trees. Use for complex code transformations, finding specific patterns across the codebase, or automated refactoring tasks that go beyond simple text matching
