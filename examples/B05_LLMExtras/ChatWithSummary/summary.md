**Instructor for PHP** is a developer-friendly, framework-agnostic PHP library designed to simplify the integration of Large Language Models (LLMs) into PHP applications. It enables the extraction of structured, validated data from LLM outputs, allowing developers to focus on application logic rather than handling complex data extraction processes. 

### Key Features:
- **Structured Responses**: Converts LLM outputs into type-safe PHP classes.
- **Multiple Input Types**: Supports text, chat messages, and images for structured data extraction.
- **Flexible Response Models**: Allows defining response models statically (via classes) or dynamically (via JSON Schema or structures).
- **Validation & Retries**: Automatically validates LLM responses and retries with feedback for self-correction.
- **Developer Experience**: Offers a simple API, customizable behavior, and minimal library footprint.
- **Unified API**: Supports multiple LLM providers (OpenAI, Anthropic, Google, Cohere, etc.) and allows easy switching between them.
- **Framework Agnostic**: Works seamlessly with any PHP framework, including Laravel and Symfony.
- **Observability**: Provides detailed event systems for monitoring, logging, and debugging LLM interactions.
- **Streaming Support**: Enables real-time partial updates for responsive applications.
- **Embeddings & Context Caching**: Unified APIs for generating embeddings and caching context to reduce inference costs and time.
  
### Use Cases:
- **E-commerce**: Product description enrichment, customer support automation, and review analysis.
- **Healthcare**: Medical record processing and clinical document analysis.
- **Education**: Course content structuring and assignment analysis.
- **Financial Services**: Document processing and risk analysis.
- **Real Estate**: Property listing analysis and document processing.
- **Recruitment & HR**: Resume processing and employee documentation.
  
### Getting Started:
1. Install the library via Composer:
   ```bash
   composer require cognesy/instructor-php
   ```
2. Define your data structure (e.g., a `City` class).
3. Use Instructor to run LLM inference and extract structured data.
   
### Example:

```php
use Cognesy\Instructor\StructuredOutput;

class City {
    public string $name;
    public string $country;
    public int $population;
}

$city = (new StructuredOutput)->withConnection('openai')->create(
    messages: 'What is the capital of France?',
    responseModel: City::class,
)->get();

var_dump($city); // Outputs structured data about Paris
```

### Advanced Features:
- **Validation**: Uses Symfony validation to ensure data integrity.
- **Max Retries**: Automatically retries failed inferences with feedback.
- **Streaming**: Supports real-time partial updates for responsive applications.
- **Dynamic Data Schemas**: Allows runtime definition of data structures using the `Structure` class.
- **Customization**: Developers can customize prompts, validation, and deserialization logic.

### Supported LLM Providers:
- OpenAI, Anthropic, Google, Cohere, Groq, Mistral, Ollama, and more.

### Documentation & Examples:
- Extensive documentation, 60+ examples, and 25+ prompting techniques are available to help developers get started.

Instructor for PHP is a powerful tool for automating data extraction and processing tasks, making it easier to integrate LLMs into PHP applications across various industries.
