<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    use HasFactory;

    protected $table = 'items';

    protected $primaryKey = 'uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'part_uuid',
        'name',
        'details',
    ];

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class, 'part_uuid', 'uuid');
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(Block::class, 'item_uuid', 'uuid');
    }
}
