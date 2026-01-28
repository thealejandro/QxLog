<?php

use App\Models\PayoutBatch;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

use function Livewire\Volt\{state, computed, mount};

state([
    // filtros
    'instrumentist_id' => '',
    'date_from' => '',
    'date_to' => '',

    // data select
    'instrumentists' => [],
]);

mount(function () {
    $user = Auth::user();
    if (!$user)
        abort(401);
    if ($user->role !== 'admin')
        abort(403);

    $this->instrumentists = User::query()
        ->where('role', 'instrumentist')
        ->orderBy('name')
        ->get(['id', 'name'])
        ->map(fn($u) => ['id' => $u->id, 'name' => $u->name])
        ->all();
});

$batches = computed(function () {
    $q = PayoutBatch::query()
        ->with([
            'instrumentist:id,name',
            'paidByUser:id,name',
        ])
        ->orderByDesc('paid_at');

    if ($this->instrumentist_id !== '' && $this->instrumentist_id !== null) {
        $q->where('instrumentist_id', (int) $this->instrumentist_id);
    }

    if ($this->date_from) {
        $q->whereDate('paid_at', '>=', $this->date_from);
    }

    if ($this->date_to) {
        $q->whereDate('paid_at', '<=', $this->date_to);
    }

    return $q->limit(100)->get();
});

?>

<div class="max-w-5xl mx-auto p-4 space-y-6">
    <div class="mb-4">
        <flux:heading size="xl">Pagos</flux:heading>
        <flux:subheading>Historial de liquidaciones</flux:subheading>
    </div>

    <div class="rounded-xl border bg-white p-6 dark:bg-zinc-900 dark:border-zinc-700 space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <flux:label>Instrumentista</flux:label>
                <select wire:model.live="instrumentist_id"
                    class="mt-2 block w-full rounded-lg border-zinc-200 bg-indigo-50/20 dark:bg-zinc-800/50 py-2.5 px-3 text-sm text-zinc-900 dark:text-zinc-100 focus:ring-0 focus:border-zinc-500">
                    <option value="">Todos</option>
                    @foreach($instrumentists as $i)
                        <option value="{{ $i['id'] }}">{{ $i['name'] }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <flux:label>Desde</flux:label>
                <input type="date" wire:model.live="date_from"
                    class="mt-2 block w-full rounded-lg border-zinc-200 bg-indigo-50/20 py-2.5 px-3 text-sm text-zinc-900 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-hidden dark:border-zinc-700 dark:bg-zinc-800/50 dark:text-zinc-100 dark:focus:border-indigo-400 hover:border-zinc-300 dark:hover:border-zinc-600 transition-colors" />
            </div>

            <div>
                <flux:label>Hasta</flux:label>
                <input type="date" wire:model.live="date_to"
                    class="mt-2 block w-full rounded-lg border-zinc-200 bg-indigo-50/20 py-2.5 px-3 text-sm text-zinc-900 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-hidden dark:border-zinc-700 dark:bg-zinc-800/50 dark:text-zinc-100 dark:focus:border-indigo-400 hover:border-zinc-300 dark:hover:border-zinc-600 transition-colors" />
            </div>
        </div>

        <div class="overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700">
            {{-- Desktop Table --}}
            <div class="hidden md:block overflow-x-auto">
                <table class="min-w-full text-sm divide-y divide-zinc-200 dark:divide-zinc-700">
                    <thead class="bg-zinc-50 dark:bg-zinc-800 text-left text-zinc-500 dark:text-zinc-400">
                        <tr>
                            <th class="px-4 py-3 font-medium">Fecha</th>
                            <th class="px-4 py-3 font-medium">Instrumentista</th>
                            <th class="px-4 py-3 font-medium text-right">Total</th>
                            <th class="px-4 py-3 font-medium">Pagado por</th>
                            <th class="px-4 py-3 font-medium text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700 bg-white dark:bg-zinc-900">
                        @forelse($this->batches as $b)
                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                                <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">
                                    {{ optional($b->paid_at)->format('Y-m-d H:i') ?? $b->paid_at }}
                                </td>
                                <td class="px-4 py-3 font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ $b->instrumentist->name ?? ('#' . $b->instrumentist_id) }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-bold text-emerald-600 dark:text-emerald-400">
                                    Q{{ number_format((float) $b->total_amount, 2) }}
                                </td>
                                <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">
                                    {{ $b->paidByUser->name ?? ('#' . $b->paid_by_user_id) }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <flux:button href="{{ route('payouts.voucher', $b->id) }}" variant="ghost" size="sm"
                                        icon="document-text">
                                        Voucher
                                    </flux:button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-zinc-500 dark:text-zinc-400">
                                    No hay pagos registrados todavía.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Mobile Cards --}}
            <div class="md:hidden divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse($this->batches as $b)
                    <div class="p-4 bg-white dark:bg-zinc-900 space-y-3">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ $b->instrumentist->name ?? ('#' . $b->instrumentist_id) }}</div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ optional($b->paid_at)->format('Y-m-d H:i') ?? $b->paid_at }}</div>
                            </div>
                            <div class="text-right">
                                <div class="font-mono font-bold text-emerald-600 dark:text-emerald-400">
                                    Q{{ number_format((float) $b->total_amount, 2) }}</div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between text-sm pt-2">
                            <div class="text-zinc-500 dark:text-zinc-400">
                                <span class="text-xs uppercase tracking-wide">Pagado por:</span>
                                {{ $b->paidByUser->name ?? ('#' . $b->paid_by_user_id) }}
                            </div>
                            <flux:button href="{{ route('payouts.voucher', $b->id) }}" variant="filled" size="sm">
                                Voucher
                            </flux:button>
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center text-zinc-500 dark:text-zinc-400">
                        No hay pagos registrados todavía.
                    </div>
                @endforelse
            </div>
        </div>

        <p class="text-xs text-zinc-500 dark:text-zinc-400 text-center">
            Mostrando máximo 100 registros por rendimiento.
        </p>
    </div>
</div>