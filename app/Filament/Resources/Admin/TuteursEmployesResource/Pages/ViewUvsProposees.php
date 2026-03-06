<?php

namespace App\Filament\Resources\Admin\TuteursEmployesResource\Pages;

use App\Enums\Roles;
use App\Filament\Resources\Admin\TuteursEmployesResource;
use App\Models\User;
use Filament\Resources\Pages\Page;

class ViewUvsProposees extends Page
{
    protected static string $resource = TuteursEmployesResource::class;
    protected static string $view = 'filament.resources.admin.tuteurs-employes-resource.view-uvs-proposees';

    public function getTitle(): string
    {
        return 'UVs proposées par les tuteur.ice.s';
    }

    public function getEmployedTutorsData()
    {
        return User::whereIn('role', [
            Roles::EmployedTutor->value,
            Roles::EmployedPrivilegedTutor->value
        ])
        ->with(['proposedUvs'])
        ->has('proposedUvs')
        ->orderBy('lastName')
        ->get();
    }

    public function getVolunteerTutorsData()
    {
        return User::where('role', Roles::Tutor->value)
            ->with(['proposedUvs'])
            ->has('proposedUvs')
            ->orderBy('lastName')
            ->get();
    }
}
