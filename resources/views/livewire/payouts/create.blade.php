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
    'instrumentist_id' => '',
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
    abort_unless((bool) $user, 401);
    abort_unless($user->can("payouts.create"), 403);

    $this->instrumentists = User::role('instrumentist')
        ->orderBy('name')
        ->get(['id', 'name'])
        ->map(fn($u) => ['id' => $u->id, 'name' => $u->name]);
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

    $allIds = $list->pluck('id')->map(fn($v) => (int) $v)->all();
    $current = array_map('intval', (array) $this->selected);

    $allSelected = count(array_diff($allIds, $current)) === 0 && count($allIds) === count($current);

    $this->selected = $allSelected ? [] : $allIds;
};

$liquidate = function () {
    $this->success_message = null;

    $admin = Auth::user();
    abort_unless((bool) $admin, 401);
    abort_unless($admin->can("payouts.create"), 403);

    $data = $this->validate();

    $selectedIds = array_values(array_unique(array_map('intval', (array) $data['selected'])));
    if (empty($selectedIds)) {
        throw ValidationException::withMessages([
            'selected' => __('Select a procedure to liquidate.'),
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
            'selected' => __('Selection changed. Reload and try again.'),
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
    $this->success_message = __('Liquidated successfully.');

    $this->dispatch('$refresh');
};

?>

<div class="max-w-6xl mx-auto p-4 space-y-6">
    <div class="mb-4">
        <flux:heading size="xl">{{ __('Liquidate Procedures') }}</flux:heading>
        <flux:subheading>{{ __('Generate payout batch') }}</flux:subheading>
    </div>

    @if($success_message)
        <div
            class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-800 dark:bg-green-900/30 dark:border-green-800 dark:text-green-300">
            {{ $success_message }}
        </div>
    @endif

    <div class="rounded-xl border bg-white p-6 dark:bg-zinc-900 dark:border-zinc-700 space-y-6">
        <div>
            <flux:select wire:model.change="instrumentist_id" label="{{ __('Instrumentist') }}"
                placeholder="{{ __('Select instrumentist') }}" empty="{{ __('Not found') }}">
                @foreach($this->instrumentists as $i)
                    <flux:select.option value="{{ $i['id'] }}">
                        {{ $i['name'] }}
                    </flux:select.option>
                @endforeach
            </flux:select>
        </div>

        @if($this->instrumentist_id)
            <div
                class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-4 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg border border-zinc-100 dark:border-zinc-700/50">
                <div class="space-y-1">
                    <div class="text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('Total pending') }}
                    </div>
                    <div class="text-xl font-bold text-emerald-600 dark:text-emerald-400">
                        Q{{ number_format($this->pending_total ?? 0, 2) }}
                    </div>
                </div>

                <div class="space-y-1">
                    <div class="text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('Total selected') }}
                    </div>
                    <div class="text-xl font-bold text-emerald-600 dark:text-emerald-400">
                        Q{{ number_format($this->selected_total ?? 0, 2) }}
                    </div>
                </div>

                <flux:button wire:click="toggleAll" variant="filled" size="sm">
                    {{ __('Select / Unselect all') }}
                </flux:button>
            </div>

            @error('selected')
                <p class="text-sm font-medium text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 p-2 rounded">
                    {{ $message }}
                </p>
            @enderror

            <div class="overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700">

                {{-- Desktop Table --}}
                <div class="hidden md:block overflow-x-auto overflow-y-auto">
                    <table class="min-w-full text-sm divide-y divide-zinc-200 dark:divide-zinc-700">
                        <thead class="bg-zinc-50 dark:bg-zinc-800 text-center text-zinc-500 dark:text-zinc-400">
                            <tr>
                                <th class="px-4 py-3 font-medium text-left">
                                    <flux:checkbox wire:click="toggleAll" />
                                </th>
                                <th class="px-4 py-3 font-medium">
                                    <flux:label>
                                        {{ __('Date') }}
                                    </flux:label>
                                </th>
                                <th class="px-4 py-3 font-medium">
                                    <div class="flex items-center justify-between">
                                        <flux:label>
                                            {{ __('Duration') }}
                                        </flux:label>
                                        <flux:badge size="sm" color="indigo">
                                            {{ __('Rules') }}
                                        </flux:badge>
                                    </div>
                                </th>
                                <th class="px-4 py-3 font-medium">
                                    <flux:label>
                                        {{ __('Patient') }}
                                    </flux:label>
                                </th>
                                <th class="px-4 py-3 font-medium">
                                    <flux:label>
                                        {{ __('Surgery') }}
                                    </flux:label>
                                </th>
                                <th class="px-4 py-3 font-medium text-right">
                                    <flux:label>
                                        {{ __('Amount') }}
                                    </flux:label>
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700 bg-white dark:bg-zinc-900">
                            @forelse($this->pending_procedures as $p)
                                <tr
                                    class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors text-sm whitespace-nowrap">
                                    <td class="px-4 py-3">
                                        <flux:checkbox wire:model.live="selected" value="{{ $p->id }}" />
                                    </td>
                                    <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">
                                        {{ $p->procedure_date->format('d/m/Y') }}
                                    </td>
                                    <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300 whitespace-nowrap text-center">
                                        <div class="flex flex-row justify-between items-center">
                                            <div class="flex flex-col items-center">
                                                <div>
                                                    {{ $p->duration_minutes }}
                                                    <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                                        {{ __('min') }}
                                                    </span>
                                                </div>
                                                <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                                    {{ Carbon\Carbon::parse($p->start_time)->format('H:i') }}
                                                    -
                                                    {{ Carbon\Carbon::parse($p->end_time)->format('H:i') }}
                                                </span>
                                            </div>
                                            <div>
                                                <x-procedure-rule-badge :rule="data_get($p, 'pricing_snapshot.rule')"
                                                    :videosurgery="$p->is_videosurgery" />
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 font-medium text-zinc-900 dark:text-zinc-100">
                                        {{ $p->patient_name }}
                                    </td>
                                    <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">
                                        {{ $p->procedure_type }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono font-bold text-emerald-600 dark:text-emerald-400">
                                        Q{{ number_format((float) $p->calculated_amount, 2) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-zinc-500 dark:text-zinc-400">
                                        {{ __('No pending procedures.') }}
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
                                        <div class="font-medium text-zinc-900 dark:text-zinc-100">
                                            {{ $p->patient_name }}
                                        </div>
                                        <div class="text-sm text-zinc-500 dark:text-zinc-400">
                                            {{ $p->procedure_type }}
                                        </div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="font-mono font-medium text-emerald-600 dark:text-emerald-400">
                                        Q{{ number_format((float) $p->calculated_amount, 2) }}
                                    </div>
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ $p->procedure_date->format('d/m/Y') }}
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center justify-between text-sm text-zinc-500 dark:text-zinc-400 pl-8">
                                <div>
                                    {{ __('Duration') }}: {{ $p->duration_minutes }} {{ __('min') }}
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ Carbon\Carbon::parse($p->start_time)->format('H:i') }} -
                                        {{ Carbon\Carbon::parse($p->end_time)->format('H:i') }}
                                    </div>
                                </div>
                                <x-procedure-rule-badge :rule="data_get($p, 'pricing_snapshot.rule')"
                                    :videosurgery="$p->is_videosurgery" />
                            </div>
                        </div>
                    @empty
                        <div class="p-8 text-center text-zinc-500 dark:text-zinc-400">
                            {{ __('No pending procedures.') }}
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="flex justify-end pt-2">
                <flux:button wire:click="liquidate" loading="liquidate" variant="primary">
                    {{ __('Liquidate selected') }}
                </flux:button>
            </div>
        @endif
    </div>
</div>