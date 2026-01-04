# Instructor PHP: Capabilities for PRM/CRM Integration

**Date:** 2026-01-04
**Context:** Evaluation of Instructor PHP for processing messy, unstructured data into clean, predefined structures for PRM/CRM systems

## Executive Summary

Instructor PHP is an AI-powered data extraction library that transforms unstructured data (text, web content, images, transcriptions) into strongly-typed PHP objects. For PRM/CRM systems, this means you can:

- **Extract lead data** from URLs, emails, social media profiles
- **Transform free-form input** (notes, emails, meeting transcripts) into structured contact/deal records
- **Process documents** (business cards, invoices, contracts) into database-ready objects
- **Validate and self-correct** extracted data to ensure CRM data quality
- **Handle uncertainty** gracefully when information is incomplete or ambiguous

## Core Capabilities for PRM/CRM Use Cases

### 1. Basic Data Extraction: Unstructured Text → Structured Records

**Use Case:** Sales rep pastes email content or meeting notes; system extracts contact information.

**Capability:** Define a PHP class representing your CRM schema, pass unstructured text, get a populated object.

**Example:**
```php
use Cognesy\Instructor\StructuredOutput;

class ContactRecord {
    public string $name;
    public string $company;
    public ?string $email;
    public ?string $phone;
    public ?string $role;
}

$notes = "Met with Sarah Johnson from Acme Corp today. She's the VP of Sales,
          reached out via sarah.j@acmecorp.com. Interested in our enterprise plan.";

$contact = (new StructuredOutput)
    ->withMessages($notes)
    ->withResponseClass(ContactRecord::class)
    ->get();

// Result:
// ContactRecord {
//   name: "Sarah Johnson"
//   company: "Acme Corp"
//   email: "sarah.j@acmecorp.com"
//   phone: null
//   role: "VP of Sales"
// }
```

**CRM Application:** Automatic contact creation from emails, meeting notes, or chat transcripts.

---

### 2. Data Validation: Ensuring CRM Data Quality

**Use Case:** Prevent invalid data (malformed emails, incorrect phone formats) from entering your CRM.

**Capability:** Use Symfony validation constraints to enforce data quality; extraction fails if data doesn't meet requirements.

**Example:**
```php
use Symfony\Component\Validator\Constraints as Assert;

class LeadRecord {
    public string $name;

    #[Assert\Email]
    #[Assert\NotBlank]
    public string $email;

    #[Assert\Regex(pattern: '/^\+?[1-9]\d{1,14}$/')]
    public ?string $phone;
}

// This will throw ValidationException if email is invalid
$lead = (new StructuredOutput)
    ->withResponseClass(LeadRecord::class)
    ->withMessages("Contact John Doe at john dot example")
    ->get();
```

**CRM Application:** Enforce data quality standards before records enter your database.

---

### 3. Self-Correction: AI Fixes Its Own Mistakes

**Use Case:** When initial extraction produces invalid data (e.g., "jason wp.pl" instead of "jason@wp.pl"), automatically retry with corrections.

**Capability:** Instructor feeds validation errors back to the LLM, which attempts to correct the data.

**Example:**
```php
class Contact {
    public string $name;

    #[Assert\Email]
    public string $email;
}

$text = "you can reply to me via jason wp.pl -- Jason";

$contact = (new StructuredOutput)
    ->with(
        messages: $text,
        responseModel: Contact::class,
        maxRetries: 3  // Allow up to 3 correction attempts
    )
    ->get();

// First attempt: email = "jason wp.pl" → INVALID
// Second attempt: email = "jason@wp.pl" → VALID ✓
```

**CRM Application:** Reduces manual data cleanup; AI learns to interpret messy input correctly.

---

### 4. Maybe Wrapper: Handling Missing or Uncertain Data

**Use Case:** Lead enrichment from web scraping might not always find complete information; handle gracefully without errors.

**Capability:** Wrap response model in `Maybe` to get either valid data OR an error explanation.

**Example:**
```php
use Cognesy\Instructor\Extras\Maybe\Maybe;

class CompanyInfo {
    public string $name;
    public string $industry;
    public int $employeeCount;
}

$webpage = "Welcome to our company!"; // Incomplete information

$maybeCompany = (new StructuredOutput)
    ->with(
        messages: $webpage,
        responseModel: Maybe::is(CompanyInfo::class)
    )
    ->get();

if ($maybeCompany->hasValue()) {
    $company = $maybeCompany->get();
    // Save to CRM
} else {
    $error = $maybeCompany->error();
    // Log: "Insufficient information to extract company details"
}
```

**CRM Application:** Graceful degradation when scraping leads from incomplete sources.

---

### 5. Sequences: Extracting Multiple Records

**Use Case:** Parse an email thread and extract all mentioned contacts/companies in one pass.

**Capability:** Extract arrays of objects without defining wrapper classes.

**Example:**
```php
use Cognesy\Instructor\Extras\Sequence\Sequence;

class Person {
    public string $name;
    public int $age;
    public string $role;
}

$email = "The project team includes Jason (25, Developer),
          Jane (18, Intern), John (30, Team Lead), and Anna (28, Designer).";

$team = (new StructuredOutput)
    ->with(
        messages: $email,
        responseModel: Sequence::of(Person::class)
    )
    ->get();

// Result: Array of 4 Person objects
```

**CRM Application:** Bulk contact import from meeting notes, org charts, or email signatures.

---

### 6. Scalars: Quick Single-Value Extraction

**Use Case:** Classify leads, extract company size, determine industry category.

**Capability:** Extract single values (strings, ints, enums) without defining classes.

**Example:**
```php
use Cognesy\Instructor\Extras\Scalar\Scalar;

enum CompanySize : string {
    case Startup = "1-50";
    case SMB = "51-500";
    case Enterprise = "500+";
}

$description = "Fast-growing SaaS startup with 35 employees and $2M ARR";

$size = (new StructuredOutput)
    ->with(
        messages: $description,
        prompt: 'What is the company size category?',
        responseModel: Scalar::enum(CompanySize::class)
    )
    ->get();

// Result: CompanySize::Startup
```

**CRM Application:** Auto-tagging, lead scoring, opportunity classification.

---

### 7. Streaming & Partial Updates: Real-Time UI Feedback

**Use Case:** Show progressive data extraction in the UI as user pastes long text or uploads documents.

**Capability:** Receive partial object updates as LLM streams its response.

**Example:**
```php
class DealRecord {
    public string $company;
    public float $dealValue;
    public string $stage;
    public array $products;
    public array $contacts;
}

$longProposal = "..."; // Multi-page proposal document

$deal = (new StructuredOutput)
    ->withMessages($longProposal)
    ->withResponseClass(DealRecord::class)
    ->withStreaming()
    ->onPartialUpdate(function($partial) {
        // Update UI progressively
        echo "Extracting: " . json_encode($partial) . "\n";
    })
    ->get();
```

**CRM Application:** Better UX for document processing; users see extraction progress live.

---

### 8. Web Content Extraction: URL → Lead Data

**Use Case:** Sales rep provides a LinkedIn profile URL or company website; extract lead information automatically.

**Capability:** Fetch web content, clean HTML, extract structured data.

**Example:**
```php
use Cognesy\Auxiliary\Web\Webpage;

class CompanyProfile {
    public string $name;
    public string $description;
    public string $industry;
    public int $employeeCount;
    public string $location;
    public array $products;
}

$companies = [];
Webpage::withScraper('none')
    ->get('https://example.com/company-directory')
    ->cleanup()
    ->selectMany('.company-card', fn($item) => $item->asMarkdown(), limit: 10);

foreach($companyGen as $companyHtml) {
    $company = (new StructuredOutput)
        ->with(
            messages: $companyHtml,
            responseModel: CompanyProfile::class
        )
        ->get();
    $companies[] = $company;
}
```

**CRM Application:** Lead enrichment from URLs, competitive intelligence, prospect research.

---

### 9. Complex Nested Extraction: Full Deal Context

**Use Case:** Extract complete deal context from project status reports or proposals (stakeholders, timeline, issues, risks).

**Capability:** Handle deeply nested structures with enums, relationships, and optional fields.

**Example:**
```php
class ProjectEvent {
    public string $title;
    public string $description;
    public ProjectEventType $type;  // enum: Risk, Issue, Action
    public ProjectEventStatus $status;  // enum: Open, Closed
    /** @var Stakeholder[] */
    public array $stakeholders;
    public ?string $date;
}

class Stakeholder {
    public string $name;
    public StakeholderRole $role;  // enum: Customer, Vendor, Partner
    public ?string $details;
}

$statusReport = "Acme Insurance project is in RED status due to delayed
                 delivery by vendor Alfatech. Customer is negotiating
                 resolution. SysCorp (integrator) deploying extra resources...";

$events = (new StructuredOutput)
    ->with(
        messages: $statusReport,
        responseModel: Sequence::of(ProjectEvent::class)
    )
    ->get();

// Result: Array of ProjectEvent objects with nested Stakeholder objects
```

**CRM Application:** Automatically update deal stages, log activities, track risks from status emails.

---

### 10. Image to Data: Business Cards, Documents, Receipts

**Use Case:** Sales rep takes photo of business card or scans invoice; extract contact/transaction data.

**Capability:** Process images with vision-enabled LLMs to extract structured data.

**Example:**
```php
use Cognesy\Addons\Image\Image;

class BusinessCard {
    public string $name;
    public string $company;
    public ?string $role;
    public ?string $email;
    public ?string $phone;
    public ?string $website;
    public ?string $address;
}

$contact = (new StructuredOutput)
    ->with(
        messages: Image::fromFile('business-card.jpg')->toMessage(),
        responseModel: BusinessCard::class,
        prompt: 'Extract contact information from this business card.'
    )
    ->get();
```

**CRM Application:** Mobile app feature for instant contact creation from photos.

---

### 11. Transcription to Tasks: Meeting Notes → Action Items

**Use Case:** Convert sales call transcripts or meeting recordings into actionable CRM tasks.

**Capability:** Transform conversational text into structured task assignments with owners, deadlines, and context.

**Example:**
```php
enum TaskStatus : string {
    case Pending = 'pending';
    case Completed = 'completed';
}

class Task {
    public string $title;
    public string $description;
    public DateTimeImmutable $dueDate;
    public string $owner;
    public TaskStatus $status;
}

class MeetingTasks {
    public DateTime $meetingDate;
    /** @var Task[] */
    public array $tasks;
}

$transcript = "Meeting on 2024-01-15: PM asked Dev to research API alternatives
               by Jan 20th. PM will update roadmap by Jan 18th...";

$tasks = (new StructuredOutput)
    ->with(
        messages: $transcript,
        responseModel: MeetingTasks::class
    )
    ->get();

// Result: MeetingTasks with array of Task objects, each with title,
//         description, due date, owner, and status
```

**CRM Application:** Automatic task creation from call notes, follow-up reminders, activity logging.

---

## Integration Patterns for PRM/CRM Systems

### Pattern 1: Lead Enrichment Pipeline
```php
// 1. Receive URL from user
$url = $_POST['company_url'];

// 2. Fetch and extract
$webpage = Webpage::withScraper('scrapingbee')->get($url)->cleanup();
$company = (new StructuredOutput)
    ->with(
        messages: $webpage->asMarkdown(),
        responseModel: Maybe::is(CompanyProfile::class)
    )
    ->get();

// 3. Handle result
if ($company->hasValue()) {
    $crm->createCompanyRecord($company->get());
} else {
    $crm->logEnrichmentFailure($url, $company->error());
}
```

### Pattern 2: Email-to-Contact Auto-Creation
```php
// 1. Monitor inbox for new emails
$email = $emailService->getLatestEmail();

// 2. Extract contacts with validation
$contacts = (new StructuredOutput)
    ->with(
        messages: $email->body,
        responseModel: Sequence::of(ContactRecord::class),
        maxRetries: 2  // Self-correction
    )
    ->get();

// 3. Deduplicate and save
foreach ($contacts as $contact) {
    if (!$crm->contactExists($contact->email)) {
        $crm->createContact($contact);
    }
}
```

### Pattern 3: Document Processing with Streaming
```php
// 1. User uploads contract/proposal
$document = $_FILES['contract'];

// 2. Extract deal terms with live progress
$deal = (new StructuredOutput)
    ->withMessages(extractText($document))
    ->withResponseClass(DealRecord::class)
    ->withStreaming()
    ->onPartialUpdate(fn($partial) =>
        broadcast('deal-extraction-progress', $partial)
    )
    ->get();

// 3. Create CRM opportunity
$crm->createOpportunity($deal);
```

---

## Key Advantages for CRM Integration

1. **Type Safety**: Extracted data matches your database schema exactly (PHP classes → database models)
2. **Built-in Validation**: Prevent bad data from entering CRM via Symfony constraints
3. **Self-Healing**: LLM retries with feedback when validation fails
4. **Flexible Input**: Text, HTML, images, transcriptions—all to the same structured output
5. **Production-Ready**: Supports streaming, retries, error handling, custom prompts
6. **Multi-Provider**: Works with OpenAI, Anthropic, Gemini, Cohere—no vendor lock-in

---

## Getting Started Checklist

For integrating Instructor into your PRM/CRM system:

- [ ] Define PHP classes matching your CRM schema (Contact, Company, Deal, Task, etc.)
- [ ] Add validation constraints (`#[Assert\Email]`, `#[Assert\NotBlank]`, etc.)
- [ ] Choose extraction sources (emails, web scraping, document uploads, transcripts)
- [ ] Implement extraction pipelines with error handling (`Maybe` wrapper)
- [ ] Add streaming for long documents to improve UX
- [ ] Set up monitoring for validation failures and self-correction metrics
- [ ] Test with real messy data (typos, incomplete info, mixed formats)

---

## Example: Complete CRM Lead Capture Flow

```php
<?php
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Extras\Maybe\Maybe;
use Cognesy\Auxiliary\Web\Webpage;
use Symfony\Component\Validator\Constraints as Assert;

// 1. Define CRM schema
class Lead {
    public string $name;
    public string $company;

    #[Assert\Email]
    public string $email;

    #[Assert\Regex(pattern: '/^\+?[1-9]\d{1,14}$/')]
    public ?string $phone;

    public ?string $industry;
    public ?string $jobTitle;
    public ?int $companySize;
}

// 2. User provides URL
$url = "https://www.linkedin.com/in/example-profile/";

// 3. Fetch content
$content = Webpage::withScraper('scrapingbee')
    ->get($url)
    ->cleanup()
    ->asMarkdown();

// 4. Extract with validation + self-correction
$maybeLead = (new StructuredOutput)
    ->with(
        messages: $content,
        responseModel: Maybe::is(Lead::class),
        maxRetries: 3
    )
    ->get();

// 5. Handle result
if ($maybeLead->hasValue()) {
    $lead = $maybeLead->get();

    // Save to CRM
    $crm->createLead([
        'name' => $lead->name,
        'company' => $lead->company,
        'email' => $lead->email,
        'phone' => $lead->phone,
        'industry' => $lead->industry,
        'job_title' => $lead->jobTitle,
        'company_size' => $lead->companySize,
        'source' => 'LinkedIn',
        'source_url' => $url,
    ]);

    echo "✓ Lead created: {$lead->name} from {$lead->company}";
} else {
    // Log failure
    error_log("Lead extraction failed: " . $maybeLead->error());
    echo "✗ Could not extract lead data from URL";
}
?>
```

---

## Conclusion

Instructor PHP transforms the challenge of "messy data into CRM" from a complex parsing/mapping problem into a simple pattern:

1. **Define** your CRM structure as PHP classes
2. **Pass** unstructured data (text, web, images)
3. **Get** validated, typed objects ready for database insertion

This dramatically reduces integration complexity while maintaining data quality and type safety.
