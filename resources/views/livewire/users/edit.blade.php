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

    // No permite quitarse el rol admin o super admin a si mismo
    if ($me->id === $this->user->id) {
        if ($data['role'] !== 'admin') {
            abort(403, 'No puedes quitarte el rol admin.');
        }

        if ($me->is_super_admin && !$data['is_super_admin']) {
            abort(403, 'No puedes quitarte el rol super_admin.');
        }
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

<div class="max-w-xl mx-auto p-4 space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Editar usuario') }}</flux:heading>
            <flux:text variant="subtle" class="text-sm">
                ID: {{ $this->user->id ?? '' }} &middot; Username: {{ $this->user->username ?? '' }}
            </flux:text>
        </div>
        <flux:link href="{{ route('users.index') }}" class="text-sm">Volver</flux:link>
    </div>

    @if($success_message)
        <flux:callout variant="success" icon="check-circle" heading="{{ $success_message }}" />
    @endif

    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 space-y-6">
        <div class="flex items-center justify-between p-4 rounded-lg bg-zinc-50 dark:bg-zinc-800/50">
            <div class="flex items-center gap-2">
                <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Estado:</span>
                <flux:badge size="sm" color="{{ $this->user->deleted_at ? 'red' : 'green' }}">
                    {{ $this->user->deleted_at ? 'Eliminado' : 'Activo' }}
                </flux:badge>
            </div>

            <flux:button wire:click="toggleDelete" wire:confirm="¿Seguro?" size="sm"
                variant="{{ $this->user->deleted_at ? 'filled' : 'danger' }}">
                {{ $this->user->deleted_at ? 'Restaurar' : 'Eliminar' }}
            </flux:button>
        </div>

        <flux:input wire:model.live="name" label="Nombre" />

        <flux:input wire:model.live="username" label="Username" />

        <flux:input wire:model.live="email" type="email" label="Email" />

        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Rol</label>
        <select wire:model.live="role"
            class="w-full rounded-lg border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 focus:ring-0 focus:border-zinc-500 p-2.5">
            <option value="admin">Admin</option>
            <option value="instrumentist">Instrumentista</option>
            <option value="doctor">Médico</option>
            <option value="circulating">Circulante</option>
        </select>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <flux:checkbox wire:model.live="is_super_admin" label="Super Admin" />
            <flux:checkbox wire:model.live="use_pay_scheme" label="Usar esquema de pago" />
        </div>

        <flux:separator />

        <div>
            <flux:heading size="lg" class="mb-4">Cambiar contraseña (opcional)</flux:heading>
            <div class="space-y-4">
                <flux:input wire:model.live="password" type="password" label="Nueva contraseña" />
                <flux:input wire:model.live="password_confirmation" type="password" label="Confirmar contraseña" />
            </div>
        </div>

        <div class="pt-2 flex justify-end">
            <flux:button variant="primary" wire:click="save" class="w-full sm:w-auto">
                {{ __('Guardar cambios') }}
            </flux:button>
        </div>
    </div>
</div>