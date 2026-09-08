<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssetCategory extends Model
{
    /** @use HasFactory<\Database\Factories\AssetCategoryFactory> */
    use HasFactory;

    protected $fillable = ['nama'];
}
