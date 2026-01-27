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
    'is_super_admin' => false,
    'use_pay_scheme' => false,
    'phone' => '',
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
    'is_super_admin' => ['boolean'],
    'use_pay_scheme' => ['boolean'],
    'phone' => ['nullable', 'string', 'max:8'],
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
        'is_super_admin' => $data['is_super_admin'],
        'use_pay_scheme' => $data['use_pay_scheme'],
        'phone' => $data['phone'],
    ]);

    $this->success_message = 'Usuario creado.';
    $this->reset([
        'name',
        'username',
        'email',
        'role',
        'password',
        'password_confirmation',
        'is_super_admin',
        'use_pay_scheme',
        'phone'
    ]);
};

?>

<div class="max-w-xl mx-auto p-4 space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Nuevo usuario') }}</flux:heading>
            <flux:subheading>{{ __('Solo Super Admin') }}</flux:subheading>
        </div>
        <flux:link href="{{ route('users.index') }}" class="text-sm">Volver</flux:link>
    </div>

    @if($success_message)
        <flux:callout variant="success" icon="check-circle" heading="{{ $success_message }}" />
    @endif

    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 space-y-6">
        <flux:input wire:model.live="name" label="Nombre" placeholder="Nombre completo" />

        <flux:input wire:model.live="username" label="Username" placeholder="usuario" />

        <flux:input wire:model.live="email" type="email" label="Email" placeholder="correo electrónico" />

        <flux:input wire:model.live="phone" label="Teléfono" placeholder="Teléfono" />

        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Rol</label>
        <select wire:model.live="role"
            class="w-full rounded-lg border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 focus:ring-0 focus:border-zinc-500 p-2.5">
            <option value="">-- Seleccionar --</option>
            <option value="admin">Admin</option>
            <option value="instrumentist">Instrumentista</option>
            <option value="doctor">Médico</option>
            <option value="circulating">Circulante</option>
        </select>

        <flux:input wire:model.live="password" type="password" label="Contraseña" />

        <flux:input wire:model.live="password_confirmation" type="password" label="Confirmar contraseña" />

        <flux:checkbox wire:model.live="is_super_admin" label="Super Admin" />

        <flux:checkbox wire:model.live="use_pay_scheme" label="Usar esquema de pago" />

        <div class="pt-2 flex justify-end">
            <flux:button variant="primary" wire:click="save" class="w-full sm:w-auto">
                {{ __('Guardar') }}
            </flux:button>
        </div>
    </div>
</div>