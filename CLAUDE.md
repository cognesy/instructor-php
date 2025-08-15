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

- Always start with extremely simple code, refactor when it is required - do not over-engineer solutions (YAGNI)
- Early returns on errors, use monadic Cognesy\Utils\Result\Result for any complex error handling
- Do not nest control structures (if, loops) beyond 1 level, exceptionally 2 levels
- Use `match` for complex conditionals, avoid `if`/`else if` chains or `switch` statements
- Use strict types and type hints for arguments and return values everywhere
- Prefer immutable data structures and functional programming paradigms
- Use DDD (Domain Driven Design) principles - aggregate roots, value objects, entities, repositories, services
- Use CQRS (Command Query Responsibility Segregation), Dependency Injection, and Inversion of Control
- Use Clean Code and SOLID principles
- Avoid using arrays as collections - use dedicated collection classes
- Use interfaces for contracts, avoid concrete class dependencies
- Avoid exceptions for control flow - do not wrap everything in try/catch

# Tests and Quality

- Use Pest for testing
- Test only services and domain logic, not external dependencies or displayed information
- Before writing tests make sure that the code is easily testable - if not propose refactorings
- Use PHPStan and Psalm for static analysis
