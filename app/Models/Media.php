<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Media extends Model
{
    use HasFactory;

    protected $table = 'media';

    protected $primaryKey = 'uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'block_uuid',
        'media_type',
        's3_key',
        's3_bucket',
        'url',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class, 'block_uuid', 'uuid');
    }
}
