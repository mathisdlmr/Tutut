<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\File;
use League\CommonMark\CommonMarkConverter;
use Illuminate\Support\Facades\Auth;
use App\Enums\Roles;

class Tutoriel extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-question-mark-circle';
    protected static string $view = 'filament.pages.tutoriel';
    protected static ?int $navigationSort = 5;
    
    public string $htmlContent;

    public function getTitle(): string
    {
        return '';
    }

    public static function getNavigationLabel(): string 
    {
        return __('resources.pages.help.title');
    }

    public function mount(): void
    {
        if(Auth::user()->role === Roles::Tutee->value) {
            $markdown = File::get(resource_path('markdown/help-tutee.md'));
        } else if(Auth::user()->role === Roles::Tutor->value) {
            $markdown = File::get(resource_path('markdown/help-tutor.md'));
        } else {
            $markdown = File::get(resource_path('markdown/help-admin.md'));
        }
        $converter = new CommonMarkConverter();
        $this->htmlContent = $converter->convertToHtml($markdown);
    }
}
