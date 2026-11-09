<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

final class SessionController
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => 'required|email|max:254', 'password' => 'required|string|max:255']);
        if (! Auth::attempt($data)) {
            throw ValidationException::withMessages(['email' => 'Email 或密碼有誤。']);
        }
        $request->session()->regenerate();

        return redirect()->intended(match ($request->user()?->role) {
            'operator', 'admin' => '/ops',
            'merchant' => route('merchant.index'),
            default => route('orders.index'),
        });
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('products.index');
    }
}
