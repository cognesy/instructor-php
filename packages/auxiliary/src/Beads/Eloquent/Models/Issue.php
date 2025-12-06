<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Eloquent\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $issue_id
 * @property string $title
 * @property string $description
 * @property string $status
 * @property int $priority
 * @property string $issue_type
 * @property string|null $assignee
 * @property string|null $design
 * @property string|null $acceptance_criteria
 * @property string|null $notes
 * @property int|null $estimated_minutes
 * @property Carbon|null $closed_at
 * @property string|null $close_reason
 * @property string|null $external_ref
 * @property array|null $labels
 * @property int|null $compaction_level
 * @property Carbon|null $compacted_at
 * @property string|null $compacted_at_commit
 * @property int|null $original_size
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, Dependency> $dependencies
 * @property-read Collection<int, Dependency> $dependents
 * @property-read Collection<int, Comment> $comments
 */
class Issue extends Model
{
    protected $table = 'beads_issues';

    protected $fillable = [
        'issue_id',
        'title',
        'description',
        'status',
        'priority',
        'issue_type',
        'assignee',
        'design',
        'acceptance_criteria',
        'notes',
        'estimated_minutes',
        'closed_at',
        'close_reason',
        'external_ref',
        'labels',
        'compaction_level',
        'compacted_at',
        'compacted_at_commit',
        'original_size',
    ];

    protected $casts = [
        'priority' => 'integer',
        'estimated_minutes' => 'integer',
        'compaction_level' => 'integer',
        'original_size' => 'integer',
        'labels' => 'array',
        'closed_at' => 'datetime',
        'compacted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the dependencies for this issue (issues this issue depends on)
     */
    public function dependencies(): HasMany
    {
        return $this->hasMany(Dependency::class, 'issue_id', 'issue_id');
    }

    /**
     * Get the dependents for this issue (issues that depend on this issue)
     */
    public function dependents(): HasMany
    {
        return $this->hasMany(Dependency::class, 'depends_on_id', 'issue_id');
    }

    /**
     * Get the comments for this issue
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'issue_id', 'issue_id');
    }

    /**
     * Scope a query to only include open issues
     */
    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    /**
     * Scope a query to only include closed issues
     */
    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }

    /**
     * Scope a query to only include in progress issues
     */
    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    /**
     * Scope a query to filter by issue type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('issue_type', $type);
    }

    /**
     * Scope a query to filter by priority
     */
    public function scopeWithPriority($query, int $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope a query to filter by assignee
     */
    public function scopeAssignedTo($query, string $assignee)
    {
        return $query->where('assignee', $assignee);
    }

    /**
     * Check if the issue is closed
     */
    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    /**
     * Check if the issue is open
     */
    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /**
     * Check if the issue is in progress
     */
    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    /**
     * Check if the issue is blocked
     */
    public function isBlocked(): bool
    {
        return $this->status === 'blocked';
    }
}
