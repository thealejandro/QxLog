<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

use function Livewire\Volt\{state, mount, rules};

state([
    'user' => null,

    'name' => '',
    'username' => '',
    'email' => '',
    'role' => '',
    'is_super_admin' => false,
    'use_pay_scheme' => false,

    'password' => '',
    'password_confirmation' => '',

    'success_message' => null,
]);

mount(function (string|int $user) {
    $me = Auth::user();
    if (!$me)
        abort(401);
    if ($me->role !== 'admin' && !$me->is_super_admin)
        abort(403);

    $u = User::withTrashed()->findOrFail($user);

    $this->user = $u;
    $this->name = $u->name;
    $this->username = $u->username;
    $this->email = $u->email;
    $this->role = $u->role;
    $this->is_super_admin = $u->is_super_admin;
    $this->use_pay_scheme = $u->use_pay_scheme;
});

rules([
    'name' => ['required', 'string', 'max:255'],
    'username' => ['required', 'string', 'max:50', 'alpha_dash'],
    'email' => ['required', 'email', 'max:255'],
    'role' => ['required', 'string', 'max:50'],
    'password' => ['nullable', 'string', 'min:6', 'confirmed'],
    'is_super_admin' => ['boolean'],
    'use_pay_scheme' => ['boolean'],
]);

$save = function () {
    $me = Auth::user();
    if ($me->role !== 'admin' && !$me->is_super_admin)
        abort(403);

    if (!$this->user)
        abort(404);

    $data = $this->validate();

    // No permitir que te bajes de super admin editándote a vos mismo
    if ($me->id === $this->user->id && $data['role'] !== 'admin') {
        abort(403, 'No puedes quitarte el rol admin.');
    } elseif ($me->is_super_admin && $data['is_super_admin'] !== 'super_admin') {
        abort(403, 'No puedes quitarte el rol super_admin.');
    }

    $this->user->update([
        'name' => $data['name'],
        'username' => $data['username'],
        'email' => $data['email'],
        'role' => $data['role'],
        'is_super_admin' => $data['is_super_admin'],
        'use_pay_scheme' => $data['use_pay_scheme'],
    ]);

    if (!empty($data['password'])) {
        $this->user->update([
            'password' => Hash::make($data['password']),
        ]);
    }

    $this->success_message = 'Usuario actualizado.';
    $this->reset(['password', 'password_confirmation']);
};

$toggleDelete = function () {
    $me = Auth::user();
    if ($me->role !== 'super_admin')
        abort(403);

    if ($me->id === $this->user->id) {
        abort(403, 'No puedes desactivar tu propio usuario.');
    }

    if ($this->user->deleted_at) {
        $this->user->restore();
    } else {
        $this->user->delete();
    }

    $this->user->refresh();
};

?>

<div class="max-w-xl mx-auto p-4">
    <div class="mb-4 flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold">
                Editar usuario
            </h1>
            <p class="text-sm text-gray-600">
                ID: {{ $this->user->id ?? '' }}
                <br>
                Username: {{ $this->user->username ?? '' }}
            </p>
        </div>
        <a href="{{ route('users.index') }}" class="underline text-sm">Volver</a>
    </div>

    @if($success_message)
        <div class="mb-4 rounded border border-green-200 bg-green-50 px-3 py-2 text-green-800">
            {{ $success_message }}
        </div>
    @endif

    <div class="rounded-lg border bg-white p-4 space-y-3">
        <div class="flex items-center justify-between">
            <div class="text-sm">
                Estado:
                @if($this->user->deleted_at)
                    <span
                        class="ml-2 inline-flex rounded bg-red-50 px-2 py-1 text-xs text-red-700 border border-red-200">Eliminado</span>
                @else
                    <span
                        class="ml-2 inline-flex rounded bg-green-50 px-2 py-1 text-xs text-green-700 border border-green-200">Activo</span>
                @endif
            </div>

            <button type="button" class="underline text-sm {{ $this->user->deleted_at ? '' : 'text-red-600' }}"
                wire:click="toggleDelete" wire:confirm="¿Seguro?">
                {{ $this->user->deleted_at ? 'Restaurar' : 'Eliminar' }}
            </button>
        </div>

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
                <option value="admin">Admin</option>
                <option value="instrumentist">Instrumentista</option>
                <option value="doctor">Médico</option>
                <option value="circulating">Circulante</option>
            </select>
            @error('role') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium">
                Super Admin
            </label>
            <input type="checkbox" class="mt-1 w-full rounded border px-3 py-2" wire:model.live="is_super_admin">
            @error('is_super_admin') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium">
                Usar esquema de pago
            </label>
            <input type="checkbox" class="mt-1 w-full rounded border px-3 py-2" wire:model.live="use_pay_scheme">
            @error('use_pay_scheme') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <hr class="my-2">

        <div class="text-sm font-medium">Cambiar contraseña (opcional)</div>

        <div>
            <label class="block text-sm font-medium">
                Nueva contraseña
            </label>
            <input type="password" class="mt-1 w-full rounded border px-3 py-2" wire:model.live="password">
            @error('password') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium">
                Confirmar contraseña
            </label>
            <input type="password" class="mt-1 w-full rounded border px-3 py-2" wire:model.live="password_confirmation">
        </div>

        <div class="pt-2">
            <button class="rounded bg-black px-4 py-2 text-white" wire:click="save">
                Guardar cambios
            </button>
        </div>
    </div>
</div>