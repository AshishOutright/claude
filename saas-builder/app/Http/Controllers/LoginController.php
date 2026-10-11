<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function show(): View
    {
        return view('login');
    }

    public function login(Request $request): RedirectResponse
    {
        $request->validate(['password' => 'required|string']);
        $expected = (string) config('builder.password');

        if ($expected === '' || ! hash_equals($expected, $request->string('password')->toString())) {
            return back()->withErrors(['password' => 'That password is not right.']);
        }

        $request->session()->regenerate();
        $request->session()->put('builder_authenticated', true);

        return redirect()->intended(route('apps.index'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
