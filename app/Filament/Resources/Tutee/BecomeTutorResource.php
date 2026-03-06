<?php

namespace App\Filament\Resources\Tutee;

use App\Enums\Roles;
use App\Filament\Resources\Tutee\BecomeTutorResource\Pages\CreateBecomeTutorRequest;
use App\Models\BecomeTutor;
use App\Models\UV;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\Auth;

/**
 * Resource de demande pour devenir tuteur
 *
 * Cette ressource permet aux tutorés de soumettre une demande pour
 * devenir tuteur dans le système.
 * Fonctionnalités :
 * - Formulaire de candidature
 * - Champs pour les informations personnelles (pré-remplis avec les données utilisateur)
 * - Indication du semestre actuel d'étude
 * - Lettre de motivation
 * - Sélection des UVs que le tutoré souhaite enseigner
 * - Affichage des demandes refusées avec message
 */
class BecomeTutorResource extends Resource
{
    protected static ?string $model = BecomeTutor::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    public static function getNavigationLabel(): string
    {
        return __('resources.become_tutor.navigation_label');
    }

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        $user = Auth::user();
        return $user && Auth::user()->role === Roles::Tutee->value;
    }

    public static function form(Form $form): Form
    {
        $currentUser = Auth::user();
        $existingRequest = $currentUser->becomeTutorRequest;

        return $form
            ->schema([
                Forms\Components\View::make('filament.components.refused.tutor-rejected')
                    ->visible($existingRequest && $existingRequest->status === 'rejected'),
                Forms\Components\Section::make(__('resources.become_tutor.section_title'))
                    ->description(__('resources.become_tutor.section_description'))
                    ->schema([
                        Forms\Components\TextInput::make('user_firstName')
                            ->label(__('resources.become_tutor.fields.firstName'))
                            ->default($currentUser->firstName)
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('user_lastName')
                            ->label(__('resources.become_tutor.fields.lastName'))
                            ->default($currentUser->lastName)
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('user_email')
                            ->label(__('resources.become_tutor.fields.email'))
                            ->email()
                            ->default($currentUser->email)
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\Hidden::make('fk_user')
                            ->default($currentUser->id),
                        Forms\Components\TextInput::make('semester')
                            ->label(__('resources.become_tutor.fields.semester'))
                            ->required()
                            ->maxLength(4)
                            ->helperText(__('resources.become_tutor.fields.semester_helper'))
                            ->regex('/^[A-Za-z]{2}[0-9]{2}$/'),
                        Forms\Components\Textarea::make('motivation')
                            ->label(__('resources.become_tutor.fields.motivation'))
                            ->required()
                            ->rows(5)
                            ->placeholder(__('resources.become_tutor.fields.motivation_placeholder')),
                        Forms\Components\Hidden::make('status')
                            ->default('pending'),
                        Forms\Components\Select::make('UVs')
                            ->label(__('resources.become_tutor.fields.uvs'))
                            ->options(UV::all()->pluck('code', 'code'))
                            ->multiple()
                            ->searchable()
                            ->required()
                            ->helperText(__('resources.become_tutor.fields.uvs_helper')),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => CreateBecomeTutorRequest::route('/'),
        ];
    }
}
