# Instructor for PHP

Turn any content into actionable data structures

Structured outputs powered by LLMs
Build robust AI-powered information processing with type safety, validation, and observability

## ABOUT
Instructor is a framework-agnostic, developer-friendly PHP library that helps you obtain LLM inference results as structured, validated data.

Instructor simplifies LLM integration into your PHP application. It handles the complexity of extracting structured data from LLM outputs, allowing you to focus on building your application's logic and iterate faster. It supports multiple input types, including text, images, and OpenAI-style chat sequence arrays.

## CAPABILITIES

Structured Responses
Turn LLM responses into type-safe data specified as PHP classes

Multiple Input Types
Process input text, chat messages, images and get structured responses

Flexible Response Models
Define response models statically as classes, or dynamically with structures or JSON Schema

Validation & Retries
Automatically validate LLM responses to get them self-corrected based on feedback

Developer Experience
Dev friendly API, customizable behavior, minimal library footprint

Unified API
Easily switch between LLM providers, choose between commercial or open source models

Framework Agnostic
Works with any PHP framework (including Laravel and Symfony), easily embeddable

Multi-platform
Pre-integrated support for OpenAI, Anthropic, Google, Cohere, Groq, and more

Observable
Detailed event system for monitoring, logging and debugging LLM interactions

Streaming Support
Real-time partial updates to make your application more responsive and engaging

Embeddings
Unified API to generate vector embeddings via multiple providers

Context Caching
Unified API for context caching, reducing cost and time of inference

## GETTING STARTED

Step 1: Install InstructorPHP package (using Composer)
$ composer require cognesy/instructor-php
Step 2: Create run.php and use Instructor
1require __DIR__ . '/vendor/autoload.php';
2
3use Cognesy\Instructor\StructuredOutput;
4
5// Set up OpenAI API key
6$apiKey = 'your-openai-api-key';
7putenv("OPENAI_API_KEY=" . $apiKey);
8// WARNING: In real project you should set up API key in .env file.
9
10// Step 1: Define target data structure(s)
11class City {
12 public string $name;
13 public string $country;
14 public int $population;
15}
16
17// Step 2: Use Instructor to run LLM inference
18$city = (new StructuredOutput)->withConnection('openai')->create(
19 messages: 'What is the capital of France?',
20 responseModel: City::class,
21)->get();
22
23var_dump($city);
Step 3: Execute run.php
$ php run.php

## LEARN
30+
chapters of documentation
Learn how to use Instructor - from basics to advanced topics.

read more

60+
ready to run examples
Instructor capabilities demonstrated in simple, runnable examples.

read more

25+
prompting techniques
Learn how to apply prompting techniques in your applications across various scenarios.

read more

15
LLM API providers
Choose from a variety of LLM API providers to power your Instructor applications.

read more

## QUOTE

"Instructor leverages the power Large Language Models to help you automate your business by turning any content sources into meaningful, actionable and structured records."

## APPLICATIONS

### E-COMMERCE & RETAIL

Product Description Enrichment
Extract structured product attributes from unstructured descriptions
Automatically categorize products into taxonomy
Benefits: faster catalog management, consistent product data

Customer Support Automation
Extract intent and entities from support tickets
Route to correct department with context
Benefits: faster response times, improved ticket classification accuracy

Review & Feedback Analysis
Extract product features, sentiment, and improvement suggestions
Structure user feedback into actionable insights
Benefits: automated insight generation, quantifiable customer satisfaction metrics

### CONTENT MANAGEMENT SYSTEMS

Automated Content Structuring
Extract product features, sentiment, and improvement suggestions
Structure user feedback into actionable insights
Benefits: automated insight generation, quantifiable customer satisfaction metrics

Media Asset Management
Extract subjects, scenes, and context from image descriptions
Generate structured tags and categories
Benefits: automated media cataloging, improved asset discovery

Content Migration
Transform legacy unstructured content into modern schemas
Extract relationships between content pieces
Benefits: faster content migration, preserved content relationships

### HEALTHCARE

Medical Record Processing
Extract patient information from unstructured notes
Structure symptoms, diagnoses, and treatments
Benefits: standardized patient data, improved care coordination

Clinical Document Analysis
Extract study parameters and results
Structure research findings into databases
Benefits: faster research synthesis, improved data accessibility

### EDUCATION & E-LEARNING

Course Content Structuring
Extract learning objectives and key concepts
Structure course materials into learning modules
Benefits: consistent course organization, improved learning paths

Assignment Analysis
Extract key points and concepts from student submissions
Structure feedback and grading criteria
Benefits: more consistent grading, faster feedback cycles

### DIGITAL AGENCIES

SEO Data Processing
Extract keywords, topics, and entities from content
Structure competitive analysis data
Benefits: automated SEO research, improved content strategy

Social Media Analysis
Extract trends, sentiment, and engagement patterns
Structure campaign performance data
Benefits: real-time campaign insights, automated reporting

### FINANCIAL SERVICES

Document Processing
Extract financial data from statements and reports
Structure transaction data and classifications
Benefits: automated financial record keeping, improved compliance

Risk Analysis
Extract risk factors and indicators from reports
Structure market analysis data
Benefits: faster risk assessment, improved decision making

### HOSPITALITY & TOURISM

Booking Analysis
Extract customer preferences and patterns
Structure feedback and review data
Benefits: personalized service delivery, improved guest experience

Inventory Management
Extract product details from supplier documents
Structure inventory and pricing data
Benefits: automated inventory updates, improved accuracy

### MANUFACTURING

Quality Control Documentation
Extract specifications and test results
Structure compliance documentation
Benefits: automated compliance checking, improved traceability

Maintenance Records
Extract service history and component data
Structure maintenance schedules and procedures
Benefits: predictive maintenance, improved equipment lifecycle

### REAL ESTATE

Property Listing Analysis
Extract property features and amenities
Structure market comparison data
Benefits: automated property valuation, improved market analysis

Document Processing
Extract terms from contracts and agreements
Structure property management data
Benefits: faster document processing, improved compliance

### RECRUITMENT & HR

Resume Processing
Extract skills, experience, and qualifications
Structure candidate profiles and matches
Benefits: automated candidate screening, improved matching accuracy

Employee Documentation
Extract policy requirements and compliance data
Structure training and development records
Benefits: automated compliance tracking, improved employee development
