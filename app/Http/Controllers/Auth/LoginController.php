<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Auth\LoginService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(
        private readonly LoginService $loginService
    ) {}

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $ok = $this->loginService->attempt(
            $request->validated('usuario'),
            $request->validated('password'),
            $request
        );

        if (! $ok) {
            return back()
                ->withInput($request->only('usuario'))
                ->withErrors([
                    'usuario' => 'Usuario o contraseña incorrectos, o la cuenta está inactiva.',
                ]);
        }

        return redirect()->intended(route('inicio'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->loginService->logout($request);

        return redirect()->route('login');
    }
}
