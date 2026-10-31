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

    /**
     * Retourne la semaine prochaine, qui n'est PAS une semaine de vacances
     * Si la semaine prochaine est une semaine de vacances, on prend la première semaine suivante qui n'en est pas une
     */
    public function nextWeek(): ?self
    {
        return static::where('fk_semestre', $this->fk_semestre)
            ->where('numero', '<>', 'X')
            ->where('date_debut', '>', $this->date_debut)
            ->orderBy('date_debut')
            ->first();
    }

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
