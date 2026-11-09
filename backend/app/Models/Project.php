<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'company_id',
    ];

    /**
     * @return BelongsTo<Company, Project>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return HasMany<Edit>
     */
    public function edits(): HasMany
    {
        return $this->hasMany(Edit::class);
    }
}
