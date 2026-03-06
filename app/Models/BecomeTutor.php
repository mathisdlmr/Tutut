<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BecomeTutor extends Model
{
    use HasFactory;

    protected $table = 'become_tutor';

    protected $fillable = [
        'fk_user',
        'semester',
        'UVs',
        'motivation',
        'status'
    ];

    protected $casts = [
        'UVs' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fk_user');
    }
}
