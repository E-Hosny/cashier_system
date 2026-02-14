<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DisplayScreenConfig extends Model
{
    use HasFactory;

    protected $table = 'display_screen_config';

    protected $fillable = ['interval_seconds'];
}
