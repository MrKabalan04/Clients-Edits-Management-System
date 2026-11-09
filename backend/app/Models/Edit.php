<?php

namespace App\Models;

use App\Enums\EditPriority;
use App\Enums\EditStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Edit extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'description',
        'priority',
        'status',
        'price',
        'is_paid',
        'due_date',
        'project_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => EditPriority::class,
            'status' => EditStatus::class,
            'price' => 'decimal:2',
            'is_paid' => 'boolean',
            'due_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Project, Edit>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return HasMany<Attachment>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }
}
