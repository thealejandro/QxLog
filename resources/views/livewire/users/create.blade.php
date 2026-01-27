<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

use function Livewire\Volt\{state, mount, rules};

state([
    'name' => '',
    'username' => '',
    'email' => '',
    'role' => '',
    'password' => '',
    'password_confirmation' => '',
    'success_message' => null,
]);

mount(function () {
    $u = Auth::user();
    if (!$u)
        abort(401);
    if ($u->role !== 'admin' && !$u->is_super_admin)
        abort(403);
});

rules([
    'name' => ['required', 'string', 'max:255'],
    'username' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('users', 'username')],
    'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
    'role' => ['required', 'string', 'max:50'],
    'password' => ['required', 'string', 'min:6', 'confirmed'],
]);

$save = function () {
    $me = Auth::user();
    if ($me->role !== 'admin' && !$me->is_super_admin)
        abort(403);

    $data = $this->validate();

    User::create([
        'name' => $data['name'],
        'username' => $data['username'],
        'email' => $data['email'],
        'role' => $data['role'],
        'password' => Hash::make($data['password']),
    ]);

    $this->success_message = 'Usuario creado.';
    $this->reset(['name', 'username', 'email', 'role', 'password', 'password_confirmation']);
};

?>

<div class="max-w-xl mx-auto p-4">
    <div class="mb-4 flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold">Nuevo usuario</h1>
            <p class="text-sm text-gray-600">Solo Super Admin</p>
        </div>
        <a href="{{ route('users.index') }}" class="underline text-sm">Volver</a>
    </div>

    @if($success_message)
        <div class="mb-4 rounded border border-green-200 bg-green-50 px-3 py-2 text-green-800">
            {{ $success_message }}
        </div>
    @endif

    <div class="rounded-lg border bg-white p-4 space-y-3">
        <div>
            <label class="block text-sm font-medium">
                Nombre
            </label>
            <input class="mt-1 w-full rounded border px-3 py-2" wire:model.live="name">
            @error('name') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium">
                Username
            </label>
            <input class="mt-1 w-full rounded border px-3 py-2" wire:model.live="username">
            @error('username') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium">
                Email
            </label>
            <input type="email" class="mt-1 w-full rounded border px-3 py-2" wire:model.live="email">
            @error('email') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium">
                Rol
            </label>
            <select class="mt-1 w-full rounded border px-3 py-2" wire:model.live="role">
                <option value="">-- Seleccionar --</option>
                <option value="admin">Admin</option>
                <option value="instrumentist">Instrumentista</option>
                <option value="doctor">Médico</option>
                <option value="circulating">Circulante</option>
            </select>
            @error('role') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium">
                Contraseña
            </label>
            <input type="password" class="mt-1 w-full rounded border px-3 py-2" wire:model.live="password">
            @error('password') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium">
                Confirmar contraseña
            </label>
            <input type="password" class="mt-1 w-full rounded border px-3 py-2" wire:model.live="password_confirmation">
            @error('password_confirmation') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="pt-2">
            <button class="rounded bg-black px-4 py-2 text-white" wire:click="save">
                Guardar
            </button>
        </div>
    </div>
</div>