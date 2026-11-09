<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Client extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'phone',
        'email',
        'address',
        'notes',
        'last_follow_up_date',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_follow_up_date' => 'date',
        ];
    }

    /**
     * @return HasMany<Company>
     */
    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }

    /**
     * @return HasManyThrough<Project, Company>
     */
    public function projects(): HasManyThrough
    {
        return $this->hasManyThrough(Project::class, Company::class);
    }
}
