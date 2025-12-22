<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlockField extends Model
{
    use HasFactory;

    protected $table = 'block_fields';

    protected $primaryKey = 'uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'block_uuid',
        'field_name',
        'field_value',
        'field_type',
    ];

    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class, 'block_uuid', 'uuid');
    }
}
