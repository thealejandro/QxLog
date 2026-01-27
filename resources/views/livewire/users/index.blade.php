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

?>

<div class="max-w-5xl mx-auto p-4">
    <div class="mb-4 flex items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">Usuarios</h1>
            <p class="text-sm text-gray-600">Solo Super Admin</p>
        </div>

        <a href="{{ route('users.create') }}" class="rounded bg-black px-4 py-2 text-white text-sm">
            Nuevo usuario
        </a>
    </div>

    <div class="rounded-lg border bg-white p-4 space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
            <div class="md:col-span-2">
                <label class="block text-sm font-medium">Buscar</label>
                <input class="mt-1 w-full rounded border px-3 py-2" placeholder="Nombre, username o email..."
                    wire:model.live="q">
            </div>

            <div>
                <label class="block text-sm font-medium">Rol</label>
                <select class="mt-1 w-full rounded border px-3 py-2" wire:model.live="role">
                    <option value="">-- Todos --</option>
                    <option value="admin">Admin</option>
                    <option value="instrumentist">Instrumentista</option>
                    <option value="doctor">Médico</option>
                    <option value="circulating">Circulante</option>
                </select>
            </div>

            <div class="flex items-end">
                <label class="inline-flex items-center gap-2 text-sm">
                    <input type="checkbox" wire:model.live="show_deleted">
                    Ver eliminados
                </label>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-left text-gray-600">
                    <tr class="border-b">
                        <th class="py-2 pr-3">Nombre</th>
                        <th class="py-2 pr-3">Username</th>
                        <th class="py-2 pr-3">Email</th>
                        <th class="py-2 pr-3">Rol</th>
                        <th class="py-2 pr-3">Estado</th>
                        <th class="py-2 pr-3">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->users as $u)
                        <tr class="border-b">
                            <td class="py-2 pr-3 font-medium">{{ $u->name }}</td>
                            <td class="py-2 pr-3">{{ $u->username }}</td>
                            <td class="py-2 pr-3">{{ $u->email }}</td>
                            <td class="py-2 pr-3">{{ $u->role }}</td>
                            <td class="py-2 pr-3">
                                @if($u->deleted_at)
                                    <span
                                        class="inline-flex rounded bg-red-50 px-2 py-1 text-xs text-red-700 border border-red-200">
                                        Eliminado
                                    </span>
                                @else
                                    <span
                                        class="inline-flex rounded bg-green-50 px-2 py-1 text-xs text-green-700 border border-green-200">
                                        Activo
                                    </span>
                                @endif
                            </td>
                            <td class="py-2 pr-3 space-x-3">
                                @if(!$u->deleted_at)
                                    <a class="text-gray-400" href="{{ route('users.edit', $u->id) }}">
                                        Editar
                                    </a>

                                    <button type="button" class="text-red-600" wire:click="deleteUser({{ $u->id }})"
                                        wire:confirm="¿Eliminar este usuario? (se puede restaurar)">
                                        <flux:icon name="trash" variant="solid" />
                                    </button>
                                @else
                                    <button type="button" class="text-indigo-600" wire:click="restoreUser({{ $u->id }})">
                                        <flux:icon name="arrow-left-start-on-rectangle" variant="solid" />
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-4 text-gray-600">No hay usuarios.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <p class="text-xs text-gray-500">Máximo 150 registros para mantenerlo ligero.</p>
    </div>
</div>