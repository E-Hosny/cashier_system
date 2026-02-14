<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DisplayScreenSlide extends Model
{
    use HasFactory;

    protected $fillable = ['path', 'sort_order'];
}
