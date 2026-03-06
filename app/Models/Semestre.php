<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Semestre extends Model
{
    use HasFactory;

    protected $table = 'semestres';

    protected $primaryKey = 'code';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['code', 'is_active', 'debut', 'fin', 'debut_medians', 'fin_medians', 'debut_finaux', 'fin_finaux'];

    public static function setActive(Semestre $semestre)
    {
        self::query()->update(['is_active' => false]);
        $semestre->update(['is_active' => true]);
    }

    public static function getActive()
    {
        return self::where('is_active', true)->first();
    }
}
