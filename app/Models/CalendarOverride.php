<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CalendarOverride extends Model
{
    use HasFactory;

    protected $fillable = ['date', 'day_template', 'is_holiday'];

    protected $casts = [
        'date' => 'date',
        'is_holiday' => 'boolean',
    ];
}
