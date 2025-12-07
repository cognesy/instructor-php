<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $issue_id
 * @property string $author
 * @property string $text
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Issue $issue
 */
class Comment extends Model
{
    protected $table = 'beads_comments';

    /** @var array<string> */
    protected $fillable = [
        'issue_id',
        'author',
        'text',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the issue that owns this comment
     */
    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class, 'issue_id', 'issue_id');
    }

    /**
     * Scope a query to filter by author
     */
    public function scopeByAuthor($query, string $author)
    {
        return $query->where('author', $author);
    }

    /**
     * Scope a query to order by creation date (newest first)
     */
    public function scopeNewest($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Scope a query to order by creation date (oldest first)
     */
    public function scopeOldest($query)
    {
        return $query->orderBy('created_at', 'asc');
    }
}
