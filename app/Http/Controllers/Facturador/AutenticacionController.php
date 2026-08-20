<?php

declare(strict_types=1);

namespace App\Http\Controllers\Facturador;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

final class AutenticacionController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if ($request->user() instanceof Usuario && $request->user()->empresa_id !== null) {
            return redirect()->route('facturador.inicio');
        }

        return view('facturador.auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credenciales = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('web')->attempt($credenciales, $request->boolean('remember'))) {
            return back()->withInput($request->only('email'))->withErrors([
                'email' => 'El correo o la contraseña no son correctos.',
            ]);
        }

        $request->session()->regenerate();
        $usuario = $request->user();

        if (! $usuario instanceof Usuario || $usuario->empresa_id === null) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors(['email' => 'Esta cuenta no pertenece a una empresa.']);
        }

        return redirect()->intended(route('facturador.inicio'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('facturador.login');
    }
}
