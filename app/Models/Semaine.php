<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Semaine extends Model
{
    use HasFactory;

    protected $table = 'semaines';

    protected $casts = [
        'date_debut' => 'datetime',
        'date_fin' => 'datetime',
    ];

    protected $fillable = [
        'id',
        'numero',
        'fk_semestre',
        'date_debut',
        'date_fin',
        'is_vacances'
    ];

    public function semestre()
    {
        return $this->belongsTo(Semestre::class, 'fk_semestre', 'code');
    }

    public function heuresSupplementaires()
    {
        return $this->hasMany(HeuresSupplementaires::class, 'fk_semaine');
    }

    public function comptabilites()
    {
        return $this->hasMany(Comptabilite::class, 'fk_semaine');
    }
}
