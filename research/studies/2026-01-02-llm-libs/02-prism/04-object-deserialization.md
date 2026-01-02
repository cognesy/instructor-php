# Prism - Object Deserialization

## Core Files
- `/src/Structured/*` - Structured output components

## Key Pattern

### Structured Output via Response Format
- Uses provider `response_format` parameter (OpenAI)
- Or prompt engineering (Anthropic)
- Similar to NeuronAI approach

## Notable Techniques

### 1. Schema-Based Validation
- JSON Schema passed to provider
- Provider enforces structure
- Reduces parsing errors

### 2. Retry Logic
- Similar to other libraries
- Error feedback to LLM
- Automatic correction

## Architecture Insights

### Strengths
1. **Provider enforcement**: Uses native features
2. **Type safety**: Schema validation

### Weaknesses
1. **Limited documentation**: Less detail than NeuronAI
2. **Provider-specific**: Not all providers support
