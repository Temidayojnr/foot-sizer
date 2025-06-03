<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Child extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $casts = [
        'foot_size_cm' => 'float',
        'priority_score' => 'integer',
    ];
}
