<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Http\Request;

// Redirigir la raíz directamente al panel de operaciones
Route::get('/', function () {
    return redirect('/admin');
});

// Acceso directo e instantáneo de 1 clic para técnicos y soporte
Route::get('/admin/quick-login', function (Request $request) {
    $user = User::firstOrCreate(
        ['email' => 'soporte@leandatech.com'],
        [
            'name' => 'Soporte Leandatech',
            'password' => \Illuminate\Support\Facades\Hash::make('admin1234')
        ]
    );

    Auth::login($user, true);
    $request->session()->regenerate();

    return redirect('/admin');
})->name('admin.quick_login');
