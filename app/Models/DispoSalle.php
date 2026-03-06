<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DispoSalle extends Model
{
    use HasFactory;

    protected $table = 'dispo_salle';

    protected $fillable = ['fk_salle', 'jour', 'debut', 'fin'];

    public function salle()
    {
        return $this->belongsTo(Salle::class, 'fk_salle', 'numero');
    }
}
