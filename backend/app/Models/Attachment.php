<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attachment extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'file_name',
        'file_data',
        'mime_type',
        'file_size',
        'description',
        'edit_id',
    ];

    /**
     * @return BelongsTo<Edit, Attachment>
     */
    public function edit(): BelongsTo
    {
        return $this->belongsTo(Edit::class);
    }
}
