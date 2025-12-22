<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Block extends Model
{
    use HasFactory;

    protected $table = 'blocks';

    protected $primaryKey = 'uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'item_uuid',
        'type',
        'description',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_uuid', 'uuid');
    }

    public function blockFields(): HasMany
    {
        return $this->hasMany(BlockField::class, 'block_uuid', 'uuid');
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class, 'block_uuid', 'uuid');
    }
}
