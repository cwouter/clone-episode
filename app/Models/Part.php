<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Part extends Model
{
    use HasFactory;

    protected $table = 'parts';

    protected $primaryKey = 'uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'episode_uuid',
        'name',
        'description',
    ];

    public function episode(): BelongsTo
    {
        return $this->belongsTo(Episode::class, 'episode_uuid', 'uuid');
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'part_uuid', 'uuid');
    }
}
