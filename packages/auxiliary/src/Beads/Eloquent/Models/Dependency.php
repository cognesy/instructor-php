<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $issue_id
 * @property string $depends_on_id
 * @property string $type
 * @property string $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Issue $issue
 * @property-read Issue $dependsOn
 */
class Dependency extends Model
{
    protected $table = 'beads_dependencies';

    /** @var array<string> */
    protected $fillable = [
        'issue_id',
        'depends_on_id',
        'type',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the issue that owns this dependency
     */
    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class, 'issue_id', 'issue_id');
    }

    /**
     * Get the issue that this issue depends on
     */
    public function dependsOn(): BelongsTo
    {
        return $this->belongsTo(Issue::class, 'depends_on_id', 'issue_id');
    }

    /**
     * Scope a query to only include dependencies of a specific type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope a query to only include blocking dependencies
     */
    public function scopeBlocks($query)
    {
        return $query->where('type', 'blocks');
    }

    /**
     * Scope a query to only include related dependencies
     */
    public function scopeRelated($query)
    {
        return $query->where('type', 'related');
    }

    /**
     * Scope a query to only include parent-child dependencies
     */
    public function scopeParentChild($query)
    {
        return $query->where('type', 'parent-child');
    }

    /**
     * Check if this is a blocking dependency
     */
    public function isBlocking(): bool
    {
        return $this->type === 'blocks';
    }

    /**
     * Check if this is a related dependency
     */
    public function isRelated(): bool
    {
        return $this->type === 'related';
    }

    /**
     * Check if this is a parent-child dependency
     */
    public function isParentChild(): bool
    {
        return $this->type === 'parent-child';
    }
}
