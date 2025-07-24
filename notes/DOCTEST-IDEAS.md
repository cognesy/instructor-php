# Doctest Enhancement Ideas

## Context

This document captures advanced enhancement ideas for the Doctest region extraction system. The basic implementation supports extracting code regions using `// @doctest-region-start name=regionName` and `// @doctest-region-end` markers, allowing documentation to include specific sections of larger code examples.

**Current Implementation (Completed):**
- Basic region extraction with named regions
- Support for multiple regions per code block
- Language-agnostic comment syntax detection
- Integration with ExtractCodeBlocks command
- Automatic generation of region-specific files (e.g., `example_setup.php`, `example_usage.php`)

**Use Case:** Enable "reverse notebook" style documentation where one comprehensive code example can be segmented and embedded piece by piece throughout documentation, eliminating redundancy and ensuring examples stay in sync.

---

## Enhancement Ideas

### 1. Literate Programming with Cross-References

**Concept**: Extend beyond simple regions to create a "notebook-style" system with named chunks that can reference each other, enabling dependency tracking and ensuring proper execution order.

**Implementation Example:**
```php
// @doctest-chunk name="imports" 
use MyApp\{Client, Config, Logger};

// @doctest-chunk name="config" depends="imports"
$config = new Config(['api_key' => env('API_KEY')]);

// @doctest-chunk name="client" depends="imports,config"
$client = new Client($config);

// @doctest-chunk name="request" depends="client" 
$response = $client->get('/users');
```

**Markdown Integration:**
```markdown
```php chunks="imports,config,client"
// Automatically includes all required chunks in dependency order
```
```

**Benefits:**
- **Dependency tracking**: Ensures chunks are included in correct order
- **Reusability**: Same chunk can be used in multiple documentation sections
- **Validation**: Ensures all dependencies are satisfied
- **Topological sorting**: Automatically resolves dependency order
- **Circular dependency detection**: Prevents infinite loops

**Implementation Notes:**
- Create `ChunkDependencyResolver` class for dependency graph management
- Extend metadata parser to handle `depends` attribute
- Add validation for missing dependencies
- Support for conditional dependencies based on context

---

### 2. Language-Agnostic Template System with Placeholders

**Concept**: Support templated regions with placeholders that can be customized per documentation context, enabling parametric examples with type safety and validation.

**Implementation Example:**
```php
// @doctest-region-start name="api_call" template
$client = new Client();
// @placeholder: endpoint | default="/users" | description="API endpoint to call"
// @placeholder: method | default="GET" | options=["GET","POST","PUT","DELETE"]
$response = $client->{{method}}('{{endpoint}}');
// @doctest-region-end
```

**Markdown Integration:**
```markdown
## Get Users
```php region="api_call" endpoint="/users" method="GET"
```

## Create User  
```php region="api_call" endpoint="/users" method="POST"
```
```

**Benefits:**
- **Parametric examples**: Same code template, different contexts
- **Type safety**: Validate placeholder values against defined options
- **Documentation**: Auto-generate parameter documentation
- **Reduced duplication**: One template, many variations
- **Consistency**: Ensures similar examples follow same patterns

**Implementation Notes:**
- Create `PlaceholderProcessor` class for template rendering
- Support for default values, validation rules, and descriptions
- Integration with existing metadata system
- Template inheritance for common patterns
- Support for computed placeholders (e.g., timestamps, UUIDs)

---

### 3. Interactive Documentation with Executable Regions

**Concept**: Create regions that can be executed independently with proper context setup and teardown, enabling live validation and interactive documentation experiences.

**Implementation Example:**
```php
// @doctest-executable name="user_crud" 
// @doctest-setup
$testDb = new TestDatabase();
$client = new Client(['base_uri' => 'http://test.api']);
// @doctest-setup-end

// @doctest-region-start name="create_user"
$userData = ['name' => 'John', 'email' => 'john@test.com'];
$response = $client->post('/users', ['json' => $userData]);
$userId = $response->json()['id'];
// @doctest-region-end

// @doctest-region-start name="get_user" 
$user = $client->get("/users/{$userId}")->json();
echo "User: " . $user['name'];
// @doctest-region-end

// @doctest-teardown
$testDb->cleanup();
// @doctest-teardown-end
```

**Features:**
- **Execution contexts**: Setup/teardown for each executable region
- **Live validation**: Ensure examples actually work during documentation build
- **Interactive playground**: Run examples in documentation sites
- **CI Integration**: Test documentation examples in pipeline
- **Isolation**: Each execution runs in clean environment

**Integration Opportunities:**
- **PHPUnit integration**: Convert regions to test cases automatically
- **Docker containerization**: Run examples in isolated containers
- **Web playground**: Execute examples in browser (like PHP Fiddle)
- **IDE integration**: Run examples directly from documentation
- **Performance monitoring**: Track execution time and resource usage

**Implementation Notes:**
- Create `ExecutableRegion` class for managing execution context
- Sandbox environment for safe code execution
- Result caching to avoid repeated expensive operations
- Support for async/long-running operations
- Integration with existing test frameworks

---

## Technical Considerations

### Standards and Tools Alignment

**Markdown Ecosystem:**
- **CommonMark compliance**: Ensure extensions don't break standard parsers
- **MDX compatibility**: Support for React-based documentation systems
- **Docusaurus integration**: Plugin system for popular documentation platforms

**Code Analysis Tools:**
- **AST parsing**: Use language-specific AST parsers for better code understanding
- **Static analysis**: Integration with tools like PHPStan, Psalm for validation
- **Dependency detection**: Automatic discovery of required imports/dependencies

**Documentation Standards:**
- **OpenAPI integration**: Generate API examples from OpenAPI specs
- **JSON Schema**: Use schemas for placeholder validation
- **Literate programming**: Draw inspiration from tools like Jupyter, R Markdown, Org-mode

### Architecture Patterns

**Plugin System:**
- Extensible processor pipeline for custom region types
- Language-specific processors for enhanced syntax support
- Output format plugins (HTML, LaTeX, PDF, etc.)

**Caching Strategy:**
- Intelligent cache invalidation based on source changes
- Distributed caching for large documentation projects
- Incremental processing for improved build times

**Error Handling:**
- Graceful degradation when regions fail to process
- Detailed error reporting with source location
- Fallback mechanisms for missing dependencies

---

## Future Research Areas

1. **Machine Learning Integration**: Use ML to suggest optimal region boundaries and dependencies
2. **Visual Documentation**: Generate diagrams from code structure and region relationships  
3. **Collaborative Editing**: Support for team-based documentation development with region locking
4. **Version Control Integration**: Track region changes across documentation versions
5. **Performance Optimization**: Lazy loading and streaming for large codebases

---

## References and Inspiration

- **Jupyter Notebooks**: Cell-based execution model
- **R Markdown**: Literate programming with embedded code
- **Org-mode**: Emacs-based literate programming system  
- **Doctest (Python)**: Executable documentation examples
- **Rust's rustdoc**: Executable code examples in documentation
- **Swift Playgrounds**: Interactive learning environment
- **Observable**: Reactive notebook environment for JavaScript

---

*Last updated: 2024-01-24*
*Status: Conceptual - Ready for Implementation Planning*