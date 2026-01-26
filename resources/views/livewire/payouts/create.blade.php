<?php

use App\Models\PayoutBatch;
use App\Models\PayoutItem;
use App\Models\Procedure;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

use function Livewire\Volt\{state, computed, mount, rules, updated};

state([
    'instrumentist_id' => null,
    'instrumentists' => [],

    // IDs de procedures seleccionados
    'selected' => [],

    // UX
    'success_message' => null,
]);

rules([
    'instrumentist_id' => ['required', 'integer', 'exists:users,id'],
    'selected' => ['array'],
    'selected.*' => ['integer', 'exists:procedures,id'],
]);

mount(function () {
    $user = Auth::user();
    if (!$user)
        abort(401);

    // Solo admin (por ahora, sin middleware)
    if ($user->role !== 'admin')
        abort(403);

    // Cargar instrumentistas para el select
    $this->instrumentists = User::query()
        ->where('role', 'instrumentist')
        ->orderBy('name')
        ->get(['id', 'name'])
        ->map(fn($u) => ['id' => $u->id, 'name' => $u->name])
        ->all();
});

updated(['instrumentist_id'], function () {
    $this->selected = [];
});

$pending_procedures = computed(function () {
    if (!$this->instrumentist_id)
        return collect();

    return Procedure::query()
        ->where('instrumentist_id', $this->instrumentist_id)
        ->where('status', 'pending')
        ->orderByDesc('procedure_date')
        ->orderByDesc('start_time')
        ->get();
});

$pending_total = computed(function () {
    if (!$this->instrumentist_id)
        return 0.0;

    return (float) Procedure::query()
        ->where('instrumentist_id', $this->instrumentist_id)
        ->where('status', 'pending')
        ->sum('calculated_amount');
});

$selected_total = computed(function () {
    $ids = array_filter(array_map('intval', (array) $this->selected));
    if (!$this->instrumentist_id || empty($ids))
        return 0.0;

    return (float) Procedure::query()
        ->where('instrumentist_id', $this->instrumentist_id)
        ->where('status', 'pending')
        ->whereIn('id', $ids)
        ->sum('calculated_amount');
});

$toggleAll = function () {
    $list = $this->pending_procedures;

    if ($list->isEmpty()) {
        $this->selected = [];
        return;
    }

    // Si ya están todos seleccionados -> deseleccionar
    $allIds = $list->pluck('id')->map(fn($v) => (int) $v)->all();
    $current = array_map('intval', (array) $this->selected);

    $allSelected = count(array_diff($allIds, $current)) === 0 && count($allIds) === count($current);

    $this->selected = $allSelected ? [] : $allIds;
};

$liquidate = function () {
    $this->success_message = null;

    $admin = Auth::user();
    if (!$admin)
        abort(401);
    if ($admin->role !== 'admin')
        abort(403);

    $data = $this->validate();

    $selectedIds = array_values(array_unique(array_map('intval', (array) $data['selected'])));
    if (empty($selectedIds)) {
        throw ValidationException::withMessages([
            'selected' => 'Selecciona al menos un procedimiento para liquidar.',
        ]);
    }

    // Re-validate contra DB: que sean del instrumentista y estén pending
    $procedures = Procedure::query()
        ->where('instrumentist_id', $data['instrumentist_id'])
        ->where('status', 'pending')
        ->whereIn('id', $selectedIds)
        ->lockForUpdate()
        ->get();

    if ($procedures->count() !== count($selectedIds)) {
        throw ValidationException::withMessages([
            'selected' => 'Tu selección cambió (alguien actualizó registros o algunos ya no están pendientes). Recarga e intenta de nuevo.',
        ]);
    }

    $total = (float) $procedures->sum('calculated_amount');

    DB::transaction(function () use ($admin, $data, $procedures, $total) {
        $batch = PayoutBatch::create([
            'instrumentist_id' => (int) $data['instrumentist_id'],
            'paid_by_id' => $admin->id,
            'paid_at' => now(),
            'total_amount' => $total,
            'status' => 'paid',
        ]);

        foreach ($procedures as $p) {
            PayoutItem::create([
                'payout_batch_id' => $batch->id,
                'procedure_id' => $p->id,
                'amount' => (float) $p->calculated_amount,
                'snapshot' => [
                    'procedure_date' => $p->procedure_date,
                    'start_time' => $p->start_time,
                    'end_time' => $p->end_time,
                    'duration_minutes' => $p->duration_minutes,
                    'patient_name' => $p->patient_name,
                    'procedure_type' => $p->procedure_type,
                    'is_videosurgery' => (bool) $p->is_videosurgery,
                    'doctor_name' => $p->doctor_name,
                    'circulating_name' => $p->circulating_name,
                    'calculated_amount' => (float) $p->calculated_amount,
                    'pricing_snapshot' => $p->pricing_snapshot,
                ],
            ]);

            $p->update([
                'status' => 'paid',
                'paid_at' => now(),
                'payout_batch_id' => $batch->id,
            ]);
        }
    });

    $this->selected = [];
    $this->success_message = 'Liquidación generada correctamente.';
    $this->dispatch('$refresh');
};

?>

<div class="max-w-4xl mx-auto p-4 space-y-6">
    <div class="mb-4">
        <flux:heading size="xl">Liquidar procedimientos</flux:heading>
        <flux:subheading>Admin • Generar bloque de pago</flux:subheading>
    </div>

    @if($success_message)
        <div
            class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-800 dark:bg-green-900/30 dark:border-green-800 dark:text-green-300">
            {{ $success_message }}
        </div>
    @endif

    <div class="rounded-xl border bg-white p-6 dark:bg-zinc-900 dark:border-zinc-700 space-y-6">
        <div>
            <flux:label>Instrumentista</flux:label>
            <select wire:model.change="instrumentist_id"
                class="mt-2 block w-full rounded-lg border-zinc-200 bg-white py-2.5 px-3 text-sm text-zinc-900 focus:border-zinc-900 focus:ring-0 focus:outline-hidden dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:focus:border-zinc-100">
                <option value="">Seleccionar instrumentista</option>
                @foreach($this->instrumentists as $i)
                    <option value="{{ $i['id'] }}">{{ $i['name'] }}</option>
                @endforeach
            </select>
        </div>

        @if($this->instrumentist_id)
            <div
                class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-4 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg border border-zinc-100 dark:border-zinc-700/50">
                <div class="space-y-1">
                    <div class="text-sm text-zinc-500 dark:text-zinc-400">Total pendiente</div>
                    <div class="text-xl font-bold text-zinc-900 dark:text-zinc-100">
                        Q{{ number_format($this->pending_total ?? 0, 2) }}</div>
                </div>

                <div class="space-y-1">
                    <div class="text-sm text-zinc-500 dark:text-zinc-400">Total seleccionado</div>
                    <div class="text-xl font-bold text-zinc-900 dark:text-zinc-100">
                        Q{{ number_format($this->selected_total ?? 0, 2) }}</div>
                </div>

                <flux:button wire:click="toggleAll" variant="filled" size="sm">
                    Seleccionar / Quitar todos
                </flux:button>
            </div>

            @error('selected')
                <p class="text-sm font-medium text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 p-2 rounded">
                    {{ $message }}
                </p>
            @enderror

            <div class="overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700">
                {{-- Desktop Table --}}
                <div class="hidden md:block overflow-x-auto">
                    <table class="min-w-full text-sm divide-y divide-zinc-200 dark:divide-zinc-700">
                        <thead class="bg-zinc-50 dark:bg-zinc-800 text-left text-zinc-500 dark:text-zinc-400">
                            <tr>
                                <th class="px-4 py-3 font-medium">Pagar</th>
                                <th class="px-4 py-3 font-medium">Fecha</th>
                                <th class="px-4 py-3 font-medium">Inicio</th>
                                <th class="px-4 py-3 font-medium">Paciente</th>
                                <th class="px-4 py-3 font-medium">Cirugía</th>
                                <th class="px-4 py-3 font-medium text-right">Monto</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700 bg-white dark:bg-zinc-900">
                            @forelse($this->pending_procedures as $p)
                                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                                    <td class="px-4 py-3">
                                        <flux:checkbox wire:model.live="selected" value="{{ $p->id }}" />
                                    </td>
                                    <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">{{ $p->procedure_date }}</td>
                                    <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">{{ $p->start_time }}</td>
                                    <td class="px-4 py-3 font-medium text-zinc-900 dark:text-zinc-100">{{ $p->patient_name }}
                                    </td>
                                    <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">
                                        <div class="flex items-center gap-2">
                                            <span>{{ $p->procedure_type }}</span>
                                            @if($p->is_videosurgery)
                                                <flux:badge size="sm" color="zinc">Video</flux:badge>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono font-medium text-zinc-900 dark:text-zinc-100">
                                        Q{{ number_format((float) $p->calculated_amount, 2) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-zinc-500 dark:text-zinc-400">
                                        No hay procedimientos pendientes para este instrumentista.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Mobile Cards --}}
                <div class="md:hidden divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($this->pending_procedures as $p)
                        <div class="p-4 bg-white dark:bg-zinc-900 space-y-3">
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex items-center gap-3">
                                    <flux:checkbox wire:model.live="selected" value="{{ $p->id }}" />
                                    <div>
                                        <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $p->patient_name }}</div>
                                        <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ $p->procedure_type }}</div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="font-mono font-medium text-zinc-900 dark:text-zinc-100">
                                        Q{{ number_format((float) $p->calculated_amount, 2) }}</div>
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $p->procedure_date }}</div>
                                </div>
                            </div>

                            <div class="flex items-center justify-between text-sm text-zinc-500 dark:text-zinc-400 pl-8">
                                <div>Inicio: {{ $p->start_time }}</div>
                                @if($p->is_videosurgery)
                                    <flux:badge size="sm" color="zinc">Video</flux:badge>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="p-8 text-center text-zinc-500 dark:text-zinc-400">
                            No hay procedimientos pendientes.
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="flex justify-end pt-2">
                <flux:button wire:click="liquidate" variant="primary" loading="liquidate">
                    Liquidar seleccionados
                </flux:button>
            </div>
        @endif
    </div>
</div>