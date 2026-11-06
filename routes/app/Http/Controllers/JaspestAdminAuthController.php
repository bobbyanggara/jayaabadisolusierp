<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class JaspestAdminAuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return Gate::allows('manage-jaspest')
                ? redirect()->intended(route('jaspest.admin.index'))
                : view('jaspest.admin.login');
        }

        return view('jaspest.admin.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:50', 'regex:/\A[a-zA-Z0-9._-]+\z/'],
            'password' => ['required', 'string'],
        ], [
            'username.required' => 'Username wajib diisi.',
            'username.max' => 'Username maksimal 50 karakter.',
            'username.regex' => 'Username hanya boleh berisi huruf, angka, titik, garis bawah, dan tanda hubung.',
            'password.required' => 'Password wajib diisi.',
        ]);

        // Username disimpan dalam format lowercase agar login tidak sensitif terhadap kapitalisasi.
        $credentials['username'] = strtolower(trim($credentials['username']));

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'username' => 'Username atau password tidak sesuai.',
            ]);
        }

        $request->session()->regenerate();

        if (! (bool) ($request->user()?->is_active ?? true)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'username' => 'Akun ini sedang dinonaktifkan. Hubungi administrator untuk mengaktifkannya kembali.',
            ]);
        }

        if (! Gate::allows('manage-jaspest')) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'username' => 'Akun ini belum diberi hak akses admin JASPEST.',
            ]);
        }

        return redirect()->intended(route('jaspest.admin.index'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('jaspest.login')
            ->with('status', 'Anda berhasil keluar dari dashboard.');
    }
}
