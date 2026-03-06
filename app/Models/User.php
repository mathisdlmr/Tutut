<?php

namespace App\Models;

use App\Enums\Roles;
use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements HasName
{
    use HasFactory;

    protected $fillable = ['email', 'firstName', 'lastName', 'role', 'languages', 'rgpd_accepted_at'];

    protected $casts = [
        'languages' => 'array',
    ];

    public function getFilamentName(): string
    {
        return ($this->firstName.' '.$this->lastName);
    }

    public function proposedUvs()
    {
        return $this->belongsToMany(UV::class, 'tutor_propose', 'fk_user', 'fk_code');
    }

    public function scopeEmployedTutors($query)
    {
        return $query->whereIn('role', [
            Roles::EmployedTutor->value,
            Roles::EmployedPrivilegedTutor->value
        ]);
    }

    public function scopeVolunteerTutors($query)
    {
        return $query->where('role', Roles::Tutor->value);
    }

    public function heuresSupplementaires()
    {
        return $this->hasMany(HeuresSupplementaires::class);
    }

    public function comptabilites()
    {
        return $this->hasMany(Comptabilite::class, 'fk_user');
    }

    public function becomeTutorRequest()
    {
        return $this->hasOne(BecomeTutor::class, 'fk_user');
    }
}
