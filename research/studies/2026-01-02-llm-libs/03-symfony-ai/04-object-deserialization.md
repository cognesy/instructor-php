# Symfony AI - Object Deserialization

## Core Files
- Structured output handled in bridges
- Uses JSON schema integration

## Key Pattern

### Structured Output via Provider Features
- **Anthropic**: Beta features for structured outputs
- **OpenAI**: Native JSON schema support
- Converts generic schema to provider format

## Notable Techniques

### 1. Schema Conversion
- Generic schema → Provider-specific format
- Handled in ModelClient during request

### 2. No Custom Deserializer
- Relies on provider enforcement
- Expects valid JSON from provider
- Standard PHP json_decode()

## Architecture Insights

### Strengths
1. **Provider enforcement**: Delegates to provider
2. **Simple**: No custom parsing needed

### Weaknesses
1. **Limited validation**: No retry on malformed JSON
2. **Provider-dependent**: Not all providers support
