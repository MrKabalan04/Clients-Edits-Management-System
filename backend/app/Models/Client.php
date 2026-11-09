<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'phone',
        'email',
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
}
