<?php

namespace App\Enums;

enum Roles : string
{
    case Administrator = 'admin';
    case EmployedPrivilegedTutor = 'employedPrivilegedTutor';
    case EmployedTutor = 'employedTutor';
    case Tutor = 'tutor';
    case Tutee = 'tutee';

    public function isAdministrator(): bool
    {
        return $this === self::Administrator;
    }

    public function isEmployedPrivilegedTutor(): bool
    {
        return $this === self::EmployedPrivilegedTutor;
    }

    public function isEmployedTutor(): bool
    {
        return $this === self::EmployedTutor;
    }

    public function isTutor(): bool
    {
        return $this === self::Tutor;
    }

    public function isTutee(): bool
    {
        return $this === self::Tutee;
    }

    public function label(): string
    {
        return match ($this) {
            self::Administrator => 'Administrateur',
            self::EmployedPrivilegedTutor => 'Tuteur privilégié rémunéré',
            self::EmployedTutor => 'Tuteur rémunéré',
            self::Tutor => 'Tuteur bénévole',
            self::Tutee => 'Tutoré',
        };
    }
}