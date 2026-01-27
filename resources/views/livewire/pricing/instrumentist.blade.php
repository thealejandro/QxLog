<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;

use function Livewire\Volt\{state, computed, mount};

state([
    'q' => '',
]);

mount(function () {
    abort_unless(Auth::check(), 401);
    abort_unless((bool) Auth::user()->is_super_admin, 403);
});

$instrumentists = computed(function () {
    return User::query()
        ->where('role', 'instrumentist')
        ->when($this->q, function ($q) {
            $term = trim($this->q);
            $q->where(function ($s) use ($term) {
                $s->where('name', 'like', "%{$term}%")
                    ->orWhere('username', 'like', "%{$term}%");
            });
        })
        ->orderBy('name')
        ->get(['id', 'name', 'username', 'use_pay_scheme']);
});

$toggle = function (int $id) {
    abort_unless((bool) Auth::user()->is_super_admin, 403);

    $u = User::query()
        ->where('role', 'instrumentist')
        ->findOrFail($id);

    $u->use_pay_scheme = !(bool) $u->use_pay_scheme;
    $u->save();
};

?>

<div class="max-w-4xl mx-auto p-4">
    <div class="mb-4">
        <h1 class="text-xl font-semibold">Instrumentistas</h1>
        <p class="text-sm text-gray-600">Marcar quién usa esquema especial de pago</p>
    </div>

    <div class="rounded-lg border bg-white p-4 space-y-4">
        <div>
            <label class="block text-sm font-medium">Buscar</label>
            <input class="mt-1 w-full rounded border px-3 py-2" placeholder="Nombre o username..." wire:model.live="q">
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-left text-gray-600">
                    <tr class="border-b">
                        <th class="py-2 pr-3">Nombre</th>
                        <th class="py-2 pr-3">Username</th>
                        <th class="py-2 pr-3">Especial</th>
                        <th class="py-2 pr-3">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->instrumentists as $u)
                        <tr class="border-b">
                            <td class="py-2 pr-3 font-medium">{{ $u->name }}</td>
                            <td class="py-2 pr-3">{{ $u->username }}</td>
                            <td class="py-2 pr-3">
                                @if($u->use_pay_scheme)
                                    <span
                                        class="inline-flex rounded bg-green-50 px-2 py-1 text-xs text-green-700 border border-green-200">Sí</span>
                                @else
                                    <span
                                        class="inline-flex rounded bg-gray-50 px-2 py-1 text-xs text-gray-700 border">No</span>
                                @endif
                            </td>
                            <td class="py-2 pr-3">
                                <button class="underline" wire:click="toggle({{ $u->id }})">
                                    Cambiar
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-4 text-gray-600">No hay instrumentistas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>