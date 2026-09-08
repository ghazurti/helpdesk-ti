<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssetMovement extends Model
{
    /** @use HasFactory<\Database\Factories\AssetMovementFactory> */
    use HasFactory;

    protected $fillable = [
        'asset_id', 'from_unit_id', 'to_unit_id', 'tanggal', 'keterangan', 'user_id',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function fromUnit()
    {
        return $this->belongsTo(Unit::class, 'from_unit_id');
    }

    public function toUnit()
    {
        return $this->belongsTo(Unit::class, 'to_unit_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
