<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comptabilite extends Model
{
    use HasFactory;

    protected $table = 'comptabilite';

    protected $fillable = [
        'nb_heures',
        'commentaire_bve',
        'saisie',
        'fk_user',
        'fk_semaine',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'fk_user');
    }

    public function semaine()
    {
        return $this->belongsTo(Semaine::class, 'fk_semaine');
    }
}
