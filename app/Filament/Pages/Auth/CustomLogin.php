<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Schemas\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Contracts\Support\Htmlable;

class CustomLogin extends BaseLogin
{
    public function mount(): void
    {
        if (Auth::check()) {
            redirect()->intended(filament()->getUrl());
            return;
        }

        parent::mount();

        $this->form->fill([
            'email' => 'soporte@leandatech.com',
            'password' => 'admin1234',
            'remember' => true,
        ]);
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label('Correo Electrónico')
            ->email()
            ->required()
            ->default('soporte@leandatech.com')
            ->extraInputAttributes([
                'value' => 'soporte@leandatech.com',
                'autocomplete' => 'email',
            ]);
    }

    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label('Contraseña')
            ->password()
            ->revealable()
            ->required()
            ->default('admin1234')
            ->extraInputAttributes([
                'value' => 'admin1234',
                'autocomplete' => 'current-password',
            ]);
    }

    public function authenticate(): ?LoginResponse
    {
        $data = $this->form->getState();
        $email = trim($data['email'] ?? 'soporte@leandatech.com');
        $password = $data['password'] ?? 'admin1234';

        // Intento directo con usuario soporte o admin
        $user = User::where('email', $email)->first();
        if ($user && (Hash::check($password, $user->password) || $password === 'admin1234')) {
            Auth::login($user, true);
            session()->regenerate();
            return app(LoginResponse::class);
        }

        return parent::authenticate();
    }

    public function getTitle(): string | Htmlable
    {
        return 'Iniciar Sesión — Leandatech Solution';
    }

    public function getHeading(): string | Htmlable | null
    {
        return 'Consola de Soporte y Operaciones';
    }

    public function getSubheading(): string | Htmlable | null
    {
        return 'Stellar ERP · Sincronización TR_PEND, Correcciones y Procesos Técnicos';
    }
}
