<?php declare(strict_types=1);

use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Tests\Support\FakeInferenceDriver;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;

/**
 * Scale profile for streaming a single complex JSON object.
 *
 * Unlike StreamingScaleProfileTest (which profiles sequences of items),
 * this test streams one large, deeply-nested JSON response as realistic
 * small token-sized chunks (10-30 chars each) through
 * FakeInferenceDriver -> StructuredOutput and measures peak memory
 * and wall-clock time at 1K, 2K, and 10K chunk counts.
 */

class ProfileAddress
{
    public string $street = '';
    public string $city = '';
    public string $state = '';
    public string $zip = '';
    public string $country = '';
}

class ProfileSkill
{
    public string $name = '';
    public int $yearsOfExperience = 0;
    public string $level = '';
}

class ProfileEmploymentEntry
{
    public string $company = '';
    public string $role = '';
    public string $startDate = '';
    public string $endDate = '';
    public string $description = '';
}

class ProfileEducationEntry
{
    public string $institution = '';
    public string $degree = '';
    public int $graduationYear = 0;
    public string $field = '';
}

class ProfileContact
{
    public string $email = '';
    public string $phone = '';
    public string $website = '';
    public string $linkedin = '';
}

class ComplexPersonProfile
{
    public string $fullName = '';
    public int $age = 0;
    public string $bio = '';
    public ProfileContact $contact;
    public ProfileAddress $address;
    /** @var ProfileSkill[] */
    public array $skills = [];
    /** @var ProfileEmploymentEntry[] */
    public array $employment = [];
    /** @var ProfileEducationEntry[] */
    public array $education = [];
    /** @var string[] */
    public array $certifications = [];
    /** @var string[] */
    public array $languages = [];
    public string $summary = '';
}

/**
 * Build a JSON string for ComplexPersonProfile sized to produce the target
 * number of chunks when split into realistic ~20-char token deltas.
 */
function buildProfileJsonForChunkCount(int $targetChunks, int $avgChunkSize = 20): string {
    $targetBytes = $targetChunks * $avgChunkSize;

    // Base structure with realistic content
    $profile = [
        'fullName' => 'Dr. Jonathan Alexander Doe',
        'age' => 42,
        'bio' => '',
        'contact' => [
            'email' => 'jonathan.doe@techcorp.example.com',
            'phone' => '+1-555-0142',
            'website' => 'https://jdoe-portfolio.example.com',
            'linkedin' => 'https://linkedin.com/in/jonathan-doe-phd',
        ],
        'address' => [
            'street' => '4521 Innovation Boulevard, Suite 300',
            'city' => 'San Francisco',
            'state' => 'CA',
            'zip' => '94105',
            'country' => 'United States',
        ],
        'skills' => [],
        'employment' => [],
        'education' => [],
        'certifications' => [],
        'languages' => [],
        'summary' => '',
    ];

    // Add realistic skill entries
    $skillNames = ['Python', 'TypeScript', 'Go', 'Rust', 'Java', 'C++', 'SQL', 'GraphQL', 'Docker', 'Kubernetes',
        'AWS', 'GCP', 'Azure', 'TensorFlow', 'PyTorch', 'React', 'Vue.js', 'Node.js', 'PostgreSQL', 'Redis',
        'Kafka', 'Elasticsearch', 'Terraform', 'Ansible', 'Jenkins', 'Git', 'Linux', 'Nginx', 'RabbitMQ', 'MongoDB'];
    $levels = ['beginner', 'intermediate', 'advanced', 'expert'];
    foreach ($skillNames as $i => $name) {
        $profile['skills'][] = [
            'name' => $name,
            'yearsOfExperience' => ($i % 15) + 1,
            'level' => $levels[$i % 4],
        ];
    }

    // Add employment entries
    $companies = [
        ['TechCorp International', 'Principal Engineer', 'Leading distributed systems architecture for global platform serving 50M users.'],
        ['DataFlow Systems', 'Senior Software Engineer', 'Designed and implemented real-time data pipeline processing 2TB daily.'],
        ['CloudScale Inc', 'Software Engineer II', 'Built microservices infrastructure supporting 99.99% uptime SLA.'],
        ['StartupXYZ', 'Full Stack Developer', 'Developed MVP to production platform, growing from 0 to 100K users.'],
        ['University Research Lab', 'Research Assistant', 'Published 3 papers on distributed consensus algorithms.'],
    ];
    foreach ($companies as $i => [$company, $role, $desc]) {
        $profile['employment'][] = [
            'company' => $company,
            'role' => $role,
            'startDate' => (2020 - $i * 3) . '-01-15',
            'endDate' => $i === 0 ? 'present' : (2020 - $i * 3 + 3) . '-12-31',
            'description' => $desc,
        ];
    }

    // Add education
    $profile['education'][] = [
        'institution' => 'Massachusetts Institute of Technology',
        'degree' => 'Ph.D. Computer Science',
        'graduationYear' => 2012,
        'field' => 'Distributed Systems and Fault Tolerance',
    ];
    $profile['education'][] = [
        'institution' => 'Stanford University',
        'degree' => 'M.S. Computer Science',
        'graduationYear' => 2008,
        'field' => 'Machine Learning and Artificial Intelligence',
    ];
    $profile['education'][] = [
        'institution' => 'University of California, Berkeley',
        'degree' => 'B.S. Electrical Engineering and Computer Science',
        'graduationYear' => 2006,
        'field' => 'Computer Architecture',
    ];

    $profile['certifications'] = [
        'AWS Solutions Architect Professional',
        'Google Cloud Professional Data Engineer',
        'Certified Kubernetes Administrator (CKA)',
        'HashiCorp Terraform Associate',
        'Certified Information Systems Security Professional (CISSP)',
    ];

    $profile['languages'] = ['English', 'Spanish', 'Mandarin', 'French', 'German'];

    // Check base size, then pad bio and summary to reach target
    $baseJson = json_encode($profile, JSON_THROW_ON_ERROR);
    $baseLen = strlen($baseJson);
    $remaining = max(0, $targetBytes - $baseLen);

    // Split padding between bio and summary
    $bioLen = (int) ($remaining * 0.6);
    $summaryLen = $remaining - $bioLen;

    $bioSentences = [
        'Dr. Doe is a seasoned principal engineer with over 15 years of experience in distributed systems. ',
        'He has led teams of 20+ engineers building planet-scale infrastructure at TechCorp International. ',
        'His research on consensus algorithms has been cited over 500 times in academic literature. ',
        'He is passionate about building reliable, maintainable software that scales gracefully. ',
        'In his spare time, he mentors junior developers and contributes to open-source projects. ',
        'He has spoken at numerous conferences including KubeCon, Strange Loop, and QCon. ',
        'His approach to software engineering emphasizes simplicity, observability, and incremental delivery. ',
        'He believes that the best systems are those that are easy to understand and easy to change. ',
    ];
    $profile['bio'] = '';
    while (strlen($profile['bio']) < $bioLen) {
        foreach ($bioSentences as $sentence) {
            $profile['bio'] .= $sentence;
            if (strlen($profile['bio']) >= $bioLen) break;
        }
    }
    $profile['bio'] = substr($profile['bio'], 0, $bioLen);

    $summarySentences = [
        'Accomplished engineer combining deep technical expertise with strong leadership capabilities. ',
        'Track record of delivering high-impact projects at scale across multiple industries. ',
        'Expert in cloud-native architectures, distributed computing, and machine learning operations. ',
        'Strong communicator who bridges the gap between technical teams and business stakeholders. ',
        'Committed to engineering excellence, continuous learning, and fostering inclusive team cultures. ',
        'Proven ability to take complex problems and break them down into manageable, iterative solutions. ',
    ];
    $profile['summary'] = '';
    while (strlen($profile['summary']) < $summaryLen) {
        foreach ($summarySentences as $sentence) {
            $profile['summary'] .= $sentence;
            if (strlen($profile['summary']) >= $summaryLen) break;
        }
    }
    $profile['summary'] = substr($profile['summary'], 0, $summaryLen);

    return json_encode($profile, JSON_THROW_ON_ERROR);
}

/**
 * Split JSON into realistic token-sized chunks (10-30 chars each),
 * with slight random variation to simulate real LLM token boundaries.
 */
function splitIntoRealisticChunks(string $json): array {
    $len = strlen($json);
    $chunks = [];
    $offset = 0;
    // Use deterministic "random" sizes cycling through realistic token lengths
    $sizes = [12, 18, 25, 15, 22, 10, 28, 20, 14, 24, 16, 30, 11, 19, 27, 13, 21, 17, 26, 23];
    $sizeIdx = 0;
    while ($offset < $len) {
        $size = $sizes[$sizeIdx % count($sizes)];
        $chunks[] = substr($json, $offset, $size);
        $offset += $size;
        $sizeIdx++;
    }
    return $chunks;
}

function runSingleObjectProfile(int $targetChunks): array {
    $json = buildProfileJsonForChunkCount($targetChunks);
    $chunks = splitIntoRealisticChunks($json);
    $actualChunkCount = count($chunks);

    $driver = new FakeInferenceDriver(
        onStream: function () use ($chunks): iterable {
            $last = count($chunks) - 1;
            foreach ($chunks as $i => $chunk) {
                yield new PartialInferenceDelta(
                    contentDelta: $chunk,
                    finishReason: $i === $last ? 'stop' : '',
                );
            }
        },
    );

    $stream = (new StructuredOutput())
        ->withRuntime(makeStructuredRuntime(
            driver: $driver,
            outputMode: OutputMode::Json,
        ))
        ->with(
            messages: 'Extract profile.',
            responseModel: ComplexPersonProfile::class,
        )
        ->withStreaming(true)
        ->stream();

    gc_collect_cycles();
    $memBefore = memory_get_usage(true);
    $peakBefore = memory_get_peak_usage(true);
    $timeBefore = hrtime(true);

    $partialCount = 0;
    foreach ($stream->partials() as $partial) {
        $partialCount++;
    }
    $result = $stream->finalValue();

    $timeAfter = hrtime(true);
    gc_collect_cycles();
    $memAfter = memory_get_usage(true);
    $peakAfter = memory_get_peak_usage(true);

    // Compute avg chunk size
    $avgChunkLen = strlen(implode('', $chunks)) / max(1, $actualChunkCount);

    return [
        'target_chunks' => $targetChunks,
        'actual_chunks' => $actualChunkCount,
        'json_bytes' => strlen($json),
        'avg_chunk_len' => round($avgChunkLen, 1),
        'partials' => $partialCount,
        'has_result' => $result !== null,
        'result_name' => $result?->fullName ?? '(null)',
        'mem_growth' => $memAfter - $memBefore,
        'peak_growth' => $peakAfter - $peakBefore,
        'time_ms' => ($timeAfter - $timeBefore) / 1_000_000,
    ];
}

it('profiles single complex object streaming at 1K, 2K, 10K chunks', function () {
    $scales = [1_000, 2_000, 10_000];
    $results = [];

    foreach ($scales as $count) {
        $results[$count] = runSingleObjectProfile($count);
    }

    // Print results table
    echo "\n\n  Single Object Stream Scale Profile (realistic ~20-char token chunks)\n";
    echo "  ┌────────┬────────┬───────────┬──────────┬──────────┬─────────────┬─────────────┬───────────┐\n";
    echo "  │ Target │ Chunks │ JSON Size │ Avg Chunk│ Partials │  Mem Growth  │ Peak Growth │  Time ms  │\n";
    echo "  ├────────┼────────┼───────────┼──────────┼──────────┼─────────────┼─────────────┼───────────┤\n";
    foreach ($results as $r) {
        echo sprintf(
            "  │ %6s │ %6s │ %9s │ %6s B │ %8s │ %11s │ %11s │ %9s │\n",
            number_format($r['target_chunks']),
            number_format($r['actual_chunks']),
            number_format($r['json_bytes']),
            number_format($r['avg_chunk_len'], 1),
            number_format($r['partials']),
            number_format($r['mem_growth']),
            number_format($r['peak_growth']),
            number_format($r['time_ms'], 1),
        );
    }
    echo "  └────────┴────────┴───────────┴──────────┴──────────┴─────────────┴─────────────┴───────────┘\n";

    // Per-chunk stats at 10K
    if ($results[10_000]['actual_chunks'] > 0) {
        $perChunk = $results[10_000]['mem_growth'] / $results[10_000]['actual_chunks'];
        $timePerChunk = $results[10_000]['time_ms'] / $results[10_000]['actual_chunks'];
        echo sprintf(
            "\n  At 10K: %.0f bytes/chunk, %.3f ms/chunk\n",
            $perChunk,
            $timePerChunk,
        );
    }

    // Result must be valid
    foreach ($results as $r) {
        expect($r['has_result'])->toBeTrue();
        expect($r['result_name'])->toBe('Dr. Jonathan Alexander Doe');
    }

    // Chunk sizes should be realistic (avg 15-25 chars)
    foreach ($results as $r) {
        expect($r['avg_chunk_len'])->toBeGreaterThan(10);
        expect($r['avg_chunk_len'])->toBeLessThan(30);
    }

    // At 10K chunks, memory should stay under 32 MB
    expect($results[10_000]['mem_growth'])->toBeLessThan(32 * 1024 * 1024, sprintf(
        'Memory grew by %s at 10K chunks — expected < 32 MB',
        number_format($results[10_000]['mem_growth']),
    ));

    // Memory growth ratio 10K/1K should be roughly linear (< 15x for 10x more chunks)
    if ($results[1_000]['mem_growth'] > 0) {
        $ratio = $results[10_000]['mem_growth'] / $results[1_000]['mem_growth'];
        expect($ratio)->toBeLessThan(15.0, sprintf(
            'Memory ratio 10K/1K = %.1fx — expected < 15x',
            $ratio,
        ));
    }

    // Time at 10K should complete within 300 seconds
    expect($results[10_000]['time_ms'])->toBeLessThan(300_000, sprintf(
        '10K chunks took %.1f ms — expected < 300s',
        $results[10_000]['time_ms'],
    ));

    // Time growth ratio — single-object partial JSON re-parsing is O(n*m) where
    // n=chunks, m=accumulated JSON size, so expect superlinear behavior.
    // Current baseline: ~30x for 10x more chunks. We cap at 50x as a regression guard.
    // Ideal target after optimization: < 15x (linear).
    if ($results[1_000]['time_ms'] > 0) {
        $timeRatio = $results[10_000]['time_ms'] / $results[1_000]['time_ms'];
        expect($timeRatio)->toBeLessThan(50.0, sprintf(
            'Time ratio 10K/1K = %.1fx — expected < 50x (current baseline ~30x, linear target ~10x)',
            $timeRatio,
        ));
    }
});
