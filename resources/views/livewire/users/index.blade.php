<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;

use function Livewire\Volt\{state, computed, mount};

state([
    'q' => '',
    'role' => '',
    'show_deleted' => false,
]);

mount(function () {
    $u = Auth::user();
    if (!$u)
        abort(401);
    if (!$u->is_super_admin)
        abort(403);
});

$users = computed(function () {
    $query = User::query()->orderBy('name');

    if ($this->show_deleted) {
        $query->onlyTrashed();
    }

    if ($this->q) {
        $q = trim($this->q);
        $query->where(function ($sub) use ($q) {
            $sub->where('name', 'like', "%{$q}%")
                ->orWhere('username', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%");
        });
    }

    if ($this->role) {
        $query->where('role', $this->role);
    }

    return $query->limit(150)->get(['id', 'name', 'username', 'email', 'role', 'deleted_at']);
});

$deleteUser = function (int $id) {
    $me = Auth::user();
    if (!$me->is_super_admin)
        abort(403);

    if ($me->id === $id) {
        abort(403, 'No puedes eliminar tu propio usuario.');
    }

    $u = User::findOrFail($id);
    $u->delete();
};

$restoreUser = function (int $id) {
    $me = Auth::user();
    if (!$me->is_super_admin)
        abort(403);

    $u = User::onlyTrashed()->findOrFail($id);
    $u->restore();
};

$roleColor = function (?string $role) {
    return match ($role) {
        'admin' => 'indigo',
        'doctor' => 'emerald',
        'instrumentist' => 'violet',
        'circulating' => 'amber',
        default => 'zinc',
    };
};

?>

<div class="max-w-5xl mx-auto p-4 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Usuarios') }}</flux:heading>
            <flux:subheading>{{ __('Solo Super Admin') }}</flux:subheading>
        </div>

        <flux:button href="{{ route('users.create') }}" icon="plus"
            class="w-full sm:w-auto !bg-indigo-500 hover:!bg-indigo-600 !border-indigo-500 !text-white dark:!bg-indigo-600 dark:hover:!bg-indigo-500">
            {{ __('Nuevo usuario') }}
        </flux:button>
    </div>

    <div class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="md:col-span-2">
                <flux:input icon="magnifying-glass" wire:model.live="q"
                    placeholder="Buscar nombre, username o email..." />
            </div>

            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Rol</label>
            <select wire:model.live="role"
                class="w-full rounded-lg border-zinc-200 bg-indigo-50/20 dark:border-zinc-700 dark:bg-zinc-800/50 text-zinc-900 dark:text-zinc-100 focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 p-2.5 hover:border-zinc-300 dark:hover:border-zinc-600 transition-colors">
                <option value="">-- Todos --</option>
                <option value="admin">Admin</option>
                <option value="instrumentist">Instrumentista</option>
                <option value="doctor">Médico</option>
                <option value="circulating">Circulante</option>
            </select>

            <div class="flex items-center">
                <flux:checkbox wire:model.live="show_deleted" label="Ver eliminados" />
            </div>
        </div>

        <!-- Mobile View (Cards) -->
        <div class="grid grid-cols-1 gap-4 sm:hidden">
            @forelse($this->users as $u)
                <div
                    class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 shadow-sm space-y-3">
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $u->name }}</div>
                            <div class="text-sm text-zinc-500">{{ $u->email }}</div>
                            <div class="text-xs text-zinc-400 font-mono mt-0.5">{{ $u->username }}</div>
                        </div>
                        <flux:badge size="sm" color="{{ $u->deleted_at ? 'red' : 'green' }}">
                            {{ $u->deleted_at ? 'Eliminado' : 'Activo' }}
                        </flux:badge>
                    </div>

                    <div class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-400">
                        <flux:badge size="sm" color="{{ $this->roleColor($u->role) }}">{{ $u->role }}</flux:badge>
                    </div>

                    <div class="pt-3 border-t border-zinc-100 dark:border-zinc-800 flex items-center justify-end gap-2">
                        @if(!$u->deleted_at)
                            <flux:button href="{{ route('users.edit', $u->id) }}" size="sm" variant="subtle">
                                {{ __('Editar') }}
                            </flux:button>

                            <flux:button wire:click="deleteUser({{ $u->id }})"
                                wire:confirm="¿Eliminar este usuario? (se puede restaurar)" size="sm" variant="danger"
                                icon="trash" />
                        @else
                            <flux:button wire:click="restoreUser({{ $u->id }})" size="sm" variant="filled"
                                icon="arrow-uturn-left">
                                {{ __('Restaurar') }}
                            </flux:button>
                        @endif
                    </div>
                </div>
            @empty
                <div class="p-4 text-center text-zinc-500 dark:text-zinc-400 italic">
                    {{ __('No hay usuarios.') }}
                </div>
            @endforelse
        </div>

        <!-- Desktop View (Table) -->
        <div class="hidden sm:block overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-800/50">
                    <tr>
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-zinc-500 uppercase tracking-wider">
                            {{ __('Nombre') }}
                        </th>
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-zinc-500 uppercase tracking-wider">
                            {{ __('Username') }}
                        </th>
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-zinc-500 uppercase tracking-wider">
                            {{ __('Email') }}
                        </th>
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-zinc-500 uppercase tracking-wider">
                            {{ __('Rol') }}
                        </th>
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-zinc-500 uppercase tracking-wider">
                            {{ __('Estado') }}
                        </th>
                        <th scope="col"
                            class="px-6 py-3 text-right text-xs font-medium text-zinc-500 uppercase tracking-wider">
                            {{ __('Acciones') }}
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-zinc-900 divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($this->users as $u)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $u->name }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-zinc-500 dark:text-zinc-400">
                                {{ $u->username }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-zinc-500 dark:text-zinc-400">{{ $u->email }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-zinc-500 dark:text-zinc-400">
                                <flux:badge size="sm" color="{{ $this->roleColor($u->role) }}">{{ $u->role }}</flux:badge>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <flux:badge size="sm" color="{{ $u->deleted_at ? 'red' : 'green' }}">
                                    {{ $u->deleted_at ? 'Eliminado' : 'Activo' }}
                                </flux:badge>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm space-x-2">
                                @if(!$u->deleted_at)
                                    <flux:button href="{{ route('users.edit', $u->id) }}" size="sm" variant="subtle">
                                        {{ __('Editar') }}
                                    </flux:button>
                                    <flux:button wire:click="deleteUser({{ $u->id }})"
                                        wire:confirm="¿Eliminar este usuario? (se puede restaurar)" size="sm" variant="danger"
                                        icon="trash" class="!px-2.5" />
                                @else
                                    <flux:button wire:click="restoreUser({{ $u->id }})" size="sm" variant="filled"
                                        icon="arrow-uturn-left" tooltip="Restaurar" />
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-sm text-zinc-500 dark:text-zinc-400 italic">
                                {{ __('No hay usuarios.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-4 text-xs text-zinc-500 dark:text-zinc-400 text-center sm:text-left">
            {{ __('Máximo 150 registros.') }}
        </div>
    </div>
</div>