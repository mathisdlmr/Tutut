<?php

namespace App\Http\Controllers;

use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RgpdController extends Controller
{
    public function show()
    {
        return view('rgpd.notice');
    }

    public function accept(Request $request)
    {
        $user = Auth::user();
        $user->rgpd_accepted_at = now();
        $user->save();

        return redirect()->intended(Filament::getUrl());
    }
}
