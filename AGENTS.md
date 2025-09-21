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
- Use PHPStan and Psalm for static analysis

# Development Tools

## ast-grep - Preferred Tool for Structural Code Refactoring

**ast-grep** is the primary tool for multi-file and codebase-wide structural code edits and refactorings. It should be prioritized over manual editing or simple text-based search/replace for any structural changes.

### When to Use ast-grep

**ALWAYS use ast-grep for:**
- Multi-file refactoring (3+ files)
- Method/function name changes across the codebase
- API migrations (changing method signatures, parameters)
- Structural pattern replacements (e.g., `$obj->oldMethod($args)` → `$obj->newMethod($args)`)
- Consistent code modernization across files
- Complex transformations that preserve syntax structure

**Prefer ast-grep over manual editing for:**
- Any change affecting 2+ files with the same pattern
- Method call transformations
- Class/interface renaming with usage updates
- Consistent code style enforcement

### PHP Pattern Syntax

ast-grep uses meta-variables to capture and reuse parts of matched code:

```yaml
# Basic method call transformation
id: refactor-method-calls
language: php
rule:
  pattern: $OBJ->oldMethodName($ARG1, $ARG2)
fix: $OBJ->newMethodName($ARG1, $ARG2)
```

**Meta-variables:**
- `$OBJ`, `$VAR`, `$EXPR` - Match any expression/variable
- `$ARG1`, `$ARG2`, `$ARGS` - Match method arguments
- `$$$` - Match variadic arguments (any number)

### Usage Examples

**Command-line search:**
```bash
ast-grep --pattern '$OBJ->methodName($ARG1, $ARG2)' --lang php packages/
```

**Rule-based transformation:**
```bash
# Create rule.yml with pattern and fix
ast-grep scan --rule rule.yml packages/ --update-all
```

**Real transformation example:**
```yaml
id: migrate-to-fluent-api
language: php
rule:
  pattern: $OBJ->withMessagesAppendedInSection($SECTION, $MESSAGES)
fix: $OBJ->section($SECTION)->appendMessages($MESSAGES)
```

### Best Practices

1. **Test patterns first** - Use `ast-grep --pattern` to verify matches before transformation
2. **Create temporary test files** - Test complex patterns on simplified examples
3. **Use descriptive rule IDs** - Make rules self-documenting
4. **Verify with tests** - Always run test suite after structural changes
5. **Commit atomically** - Each ast-grep transformation should be a separate commit

### Rule File Structure

```yaml
id: descriptive-rule-name
language: php
rule:
  pattern: # What to match
fix: # What to replace it with
```

**Pro tip:** ast-grep preserves exact syntax structure, making it superior to regex for code transformations.

# Commit Message Guidelines

- Use [Conventional Commits](https://www.conventionalcommits.org/en/v1.0.0/) format
- Include a brief description of the change, its motivation, and any relevant issue references
- Never mention CLAUDE in commit messages or code comments
