<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkCategory extends Model
{
    /** @use HasFactory<\Database\Factories\WorkCategoryFactory> */
    use HasFactory;

    protected $fillable = ['nama'];
}
