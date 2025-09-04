# Instructor PHP Monorepo - Package Architecture Map

Strategic overview of the Instructor PHP ecosystem - a comprehensive AI/LLM integration framework with structured output extraction capabilities.

## Executive Summary

The Instructor PHP monorepo provides a complete toolkit for integrating LLM/AI capabilities into PHP applications with strict type safety, structured output generation, and enterprise-grade infrastructure. The architecture follows clean separation of concerns with 10+ specialized packages that handle everything from low-level HTTP communication to high-level AI response parsing.

---

## Package Responsibilities & Capabilities

### **Instructor** - Main Orchestration Layer
**Role**: Primary facade and structured output engine  
**Responsibility**: End-to-end LLM interaction with strict type safety

**Core Capabilities**:
- **StructuredOutput**: Main entry point with fluent API for configuring LLM requests, response models, validation, and retries
- **Response Processing**: Handles both streaming and non-streaming responses with partial updates and sequence handling
- **Validation & Transformation**: Built-in retry mechanisms with custom retry prompts for validation failures
- **Dynamic Structure Support**: Integration with `Maybe<T>`, `Sequence<T>`, `Structure`, and `Scalar` for flexible data modeling
- **Event Integration**: Comprehensive event system for observability and debugging with wiretap capabilities
- **Multi-Provider Support**: Seamless integration with OpenAI, Anthropic, Cohere, and custom LLM providers

**Key Integration Points**: Uses all other packages as dependencies - serves as the main orchestration layer that coordinates schema generation, message handling, HTTP communication, and response validation.

---

### **Polyglot** - LLM Provider Abstraction
**Role**: Universal LLM provider interface and driver management  
**Responsibility**: Abstract away provider-specific implementations

**Core Capabilities**:
- **Inference API**: Universal interface supporting OpenAI, Anthropic, Cohere, Groq, Azure, and custom providers
- **Embeddings API**: Text vectorization across multiple embedding providers with consistent response format
- **Streaming Support**: Real-time response processing with partial response handling and tool call extraction
- **Provider Management**: DSN-based configuration, preset management, and dynamic driver registration
- **Request/Response Abstraction**: Normalized interfaces hiding provider-specific API differences
- **Tool Integration**: Seamless function calling support across different LLM providers
- **Context Management**: Cached context handling for performance optimization

**Key Integration Points**: Core dependency for Instructor package; uses HTTP Client for communication, Events for observability, Config for provider settings, and Messages for request formatting.

---

### **Messages** - Communication Protocol Layer
**Role**: LLM communication protocol and message formatting  
**Responsibility**: Standardize message formats across all providers

**Core Capabilities**:
- **Message System**: Structured message handling with role-based typing (system, user, assistant, tool, developer)
- **Content Management**: Multi-part content support including text, images, files, and audio with automatic complexity detection
- **Format Conversion**: Seamless conversion between string, array, and object representations
- **Media Integration**: Built-in Image, File, and Audio utilities with base64 and URL support
- **Collection Operations**: Advanced message collection operations including filtering, merging, and role-based partitioning
- **Provider Compatibility**: OpenAI-compatible message format with extensibility for other providers

**Key Integration Points**: Used by Instructor and Polyglot for all LLM communication; integrates with Templates for message generation and Utils for data transformation.

---

### **Schema** - Type System Foundation
**Role**: PHP type system analysis and JSON Schema generation  
**Responsibility**: Bridge between PHP types and LLM-compatible schemas

**Core Capabilities**:
- **TypeDetails System**: Comprehensive PHP type analysis including scalars, objects, enums, collections, and complex nested structures
- **Schema Generation**: Automatic JSON Schema creation from PHP classes, DocBlocks, and TypeHints
- **Class Introspection**: Deep analysis of class properties, constructors, methods, and requirements using Reflection
- **Visitor Pattern**: Extensible schema transformation system for multiple output formats
- **Reference Management**: Object reference handling for complex schemas with circular dependencies
- **Tool Call Generation**: OpenAI function call schema generation with proper reference resolution

**Key Integration Points**: Foundation for Instructor's response model system; used by Dynamic for runtime structure creation, and Polyglot for tool call schemas.

---

### **Dynamic** - Runtime Structure Builder
**Role**: Runtime data structure generation and manipulation  
**Responsibility**: Create and manage flexible data structures without compile-time classes

**Core Capabilities**:
- **Structure Factory**: Create dynamic structures from classes, arrays, strings, function signatures, and JSON schemas
- **Field System**: Type-safe field definitions with validation, default values, and optionality
- **Runtime Validation**: Field-level and structure-level validation with custom validators
- **Serialization Engine**: Bidirectional conversion between PHP objects and arrays/JSON
- **Collection Prototypes**: Managed collection handling with item type enforcement
- **Schema Integration**: Full integration with Schema package for type analysis and JSON Schema generation

**Key Integration Points**: Powers Instructor's `Structure`, `Field`, and `StructureFactory` APIs; uses Schema for type analysis and Utils for data transformation.

---

### **Templates** - Prompt Management System
**Role**: Template engine abstraction and prompt generation  
**Responsibility**: Manage and render LLM prompts from templates

**Core Capabilities**:
- **Multi-Engine Support**: Twig, Blade, and ArrowPipe template engine integration with unified API
- **Template Provider**: Configuration management with preset support and DSN-based initialization
- **Script System**: Multi-section prompt management with parameter injection and section selection
- **Message Generation**: Direct conversion from templates to Messages format for LLM consumption
- **Variable Management**: Template variable extraction, validation, and parameter management
- **XML Chat Format**: Specialized chat markup for conversational prompt structures

**Key Integration Points**: Generates Messages for Polyglot consumption; uses Config for template path resolution and Events for template processing observability.

---

### **Pipeline** - Processing Infrastructure
**Role**: Data processing pipeline with middleware and state management  
**Responsibility**: Provide robust processing infrastructure for complex operations

**Core Capabilities**:
- **State Management**: Immutable state containers with Result monad pattern and tag-based metadata
- **Middleware System**: Pipeline middleware, step hooks, and around-each processors
- **Error Handling**: Configurable error strategies (fail-fast vs. continue-on-failure)
- **Observation System**: Built-in timing, memory tracking, and step-level observability
- **Batch Processing**: Efficient processing of multiple inputs with streaming support
- **Tag System**: Flexible metadata system for tracking processing state and context

**Key Integration Points**: Used by Instructor for response processing pipelines; integrates with Events for processing observability and Utils for state transformations.

---

### **Events** - Observability Infrastructure
**Role**: Event-driven architecture and observability  
**Responsibility**: Provide comprehensive event system for monitoring and debugging

**Core Capabilities**:
- **Event Dispatcher**: PSR-14 compliant event system with priority-based listener management
- **Framework Integration**: Native Laravel and Symfony event dispatcher bridges
- **Wiretap System**: Global event observation for debugging and monitoring
- **Event Hierarchy**: Structured event inheritance with automatic type-based listener matching
- **Logging Integration**: Built-in log formatting with configurable log levels and console output
- **Propagation Control**: StoppableEvent support with fine-grained control flow

**Key Integration Points**: Core observability layer used across all packages; provides HandlesEvents trait for easy integration into any class.

---

### **HTTP Client** - Communication Layer
**Role**: HTTP communication abstraction with middleware support  
**Responsibility**: Handle all external HTTP communication with advanced features

**Core Capabilities**:
- **Client Builder**: Fluent HTTP client configuration with preset support and DSN configuration
- **Middleware Stack**: Request/response middleware with named middleware management
- **Streaming Support**: Real-time response streaming with line-by-line processing
- **Record/Replay**: HTTP interaction recording for testing and development
- **Driver Abstraction**: Pluggable HTTP drivers with Guzzle as default implementation
- **Event Integration**: HTTP event emission for request/response observability

**Key Integration Points**: Used by Polyglot for all LLM provider communication; integrates with Events for HTTP observability and Config for client configuration.

---

### **Config** - Configuration Management
**Role**: Application configuration and environment management  
**Responsibility**: Unified configuration system across all packages

**Core Capabilities**:
- **Multi-Provider Chain**: Cascading configuration providers with priority resolution
- **Framework Adapters**: Laravel, Symfony integration with native configuration systems
- **Environment Variables**: .env file loading with BasePath auto-detection
- **DSN Parsing**: Structured DSN parsing with type-safe parameter extraction and template variable support
- **Preset Management**: Configuration presets with default fallback and validation
- **Path Resolution**: Intelligent base path detection across multiple deployment scenarios

**Key Integration Points**: Foundation configuration layer used by all packages; provides unified configuration access patterns and environment variable management.

---

### **Utils** - Foundation Utilities
**Role**: Core utility functions and data structures  
**Responsibility**: Provide fundamental building blocks for all other packages

**Core Capabilities**:
- **Data Structures**: DataMap (nested data access), Context (service container), Cached (lazy evaluation)
- **Result Pattern**: Monadic error handling with transformation and recovery operations
- **String Processing**: Case conversion, search operations, text manipulation, and templating
- **Array Operations**: Advanced array manipulation, validation, and transformation utilities
- **JSON Processing**: Resilient JSON parsing, partial JSON handling, and streaming JSON support
- **File System**: Directory operations, file manipulation, and recursive processing
- **Profiling**: Performance measurement and memory tracking utilities

**Key Integration Points**: Foundation layer used by all packages; provides core data structures, error handling patterns, and utility functions.

---

## Architectural Patterns & Principles

### **Monadic Error Handling**
- Result pattern implementation in Utils for error propagation
- Used across Instructor, Polyglot, and Pipeline for failure handling
- Eliminates exception-based control flow in favor of explicit error types

### **Immutable Data Structures**  
- All Messages, Content, and Structure objects are immutable
- State transformation through builder patterns and fluent APIs
- Memory-efficient operations with structural sharing

### **Provider Pattern**
- Consistent abstraction across HTTP clients, template engines, and LLM providers
- Plugin architecture for extensibility
- DSN-based configuration for uniform setup

### **Event-Driven Architecture**
- Comprehensive observability through Events package
- Cross-cutting concerns handled via event emission
- Integration points for external monitoring and logging

### **Schema-First Design**
- TypeDetails system provides single source of truth for type information
- Automatic JSON Schema generation from PHP types
- Runtime validation aligned with compile-time type safety

### **Layered Architecture**
- Clear separation between infrastructure (HTTP, Config), domain logic (Schema, Dynamic), and application layer (Instructor, Templates)
- Each layer only depends on layers below it
- Cross-cutting concerns handled by Utils and Events

## Strategic Integration Points

### **Primary Data Flow**
1. **Input Processing**: Templates → Messages → Polyglot
2. **Schema Generation**: Schema → Dynamic → JSON Schema
3. **LLM Communication**: HTTP Client ← Polyglot ← Provider APIs
4. **Response Processing**: Pipeline → Validation → Structured Output
5. **Observability**: Events → Logging/Monitoring across all layers

### **Configuration Flow**
- Config provides unified configuration access
- DSN-based provider configuration
- Environment-specific settings with preset management
- Framework integration for existing applications

### **Error Handling Strategy**
- Result pattern for functional error handling
- Event emission for error observability
- Retry mechanisms with custom prompts
- Graceful degradation for non-critical failures

This architecture provides a complete, enterprise-ready solution for AI/LLM integration with strong type safety, comprehensive observability, and extensible design patterns.