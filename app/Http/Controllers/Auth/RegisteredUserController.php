<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'negocio' => 'nullable|string|max:255',
            'tipo_cliente' => 'required|in:particular,negocio',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'celular' => 'required|string|max:50',
            'direccion' => 'required|string|max:500',
            'ciudad' => 'required|string|max:255',
            'provincia' => 'nullable|string|max:255',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'negocio' => $request->negocio,
            'tipo_cliente' => $request->tipo_cliente,
            'email' => $request->email,
            'celular' => $request->celular,
            'direccion' => $request->direccion,
            'ciudad' => $request->ciudad,
            'provincia' => $request->provincia,
            'password' => Hash::make($request->password),
        ]);

        event(new Registered($user));

        Auth::login($user);

        session()->put('mostrar_guia_bienvenida', true);

        return redirect(route('dashboard', absolute: false));
    }
}
