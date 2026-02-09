<?php

use App\Models\Procedure;
use App\Models\PayoutBatch;
use Illuminate\Support\Facades\Auth;

use function Livewire\Volt\{state, mount};

state([
    // Pending
    'pending' => [],
    'pending_count' => 0,
    'pending_total' => 0.0,

    // History
    'batches' => [],
    'from' => null,
    'to' => null,
]);

mount(function () {
    $user = Auth::user();
    abort_unless(Auth::check(), 401);

    // ======================
    // Pending procedures
    // ======================
    $pendingBase = Procedure::query()
        ->where('instrumentist_id', $user->id)
        ->where('status', 'pending');

    $this->pending_count = (int) $pendingBase->count();
    $this->pending_total = (float) $pendingBase->sum('calculated_amount');

    $this->pending = $pendingBase
        ->orderByDesc('procedure_date')
        ->orderByDesc('created_at')
        ->limit(50)
        ->get();

    // ======================
    // Paid batches history
    // ======================
    $batchesQ = PayoutBatch::query()
        ->where('instrumentist_id', $user->id)
        ->withCount('items')
        ->orderByDesc('paid_at');

    if ($this->from) {
        $batchesQ->whereDate('paid_at', '>=', $this->from);
    }
    if ($this->to) {
        $batchesQ->whereDate('paid_at', '<=', $this->to);
    }

    $this->batches = $batchesQ->limit(100)->get();
});

$updatedFrom = fn() => $this->load();
$updatedTo = fn() => $this->load();

$ruleLabel = function (?string $rule) {
    return match ($rule) {
        'video_rate' => __('Video'),
        'night_rate' => __('Night'),
        'long_case_rate' => __('Long'),
        default => __('Base Rate'),
    };
};

$ruleColor = function (?string $rule) {
    return match ($rule) {
        'video_rate' => 'indigo',
        'night_rate' => 'rose',
        'long_case_rate' => 'amber',
        default => 'zinc',
    };
};

?>

<div class="max-w-6xl mx-auto p-4 space-y-6">
    <div class="flex items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ __('My Payouts') }}</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('Pending balance and paid vouchers.') }}
            </p>
        </div>
    </div>

    {{-- Pending --}}
    <div class="rounded-xl border bg-white p-6 dark:bg-zinc-900 dark:border-zinc-700">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Pending') }}</h2>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('Estimated total of procedures not yet paid.') }}
                </p>
            </div>

            <div class="text-right">
                <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Procedures') }}</div>
                <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-100 font-mono">
                    {{ $this->pending_count }}
                </div>

                <div class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Estimated') }}</div>
                <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-100 font-mono">
                    Q{{ number_format((float) $this->pending_total, 2) }}
                </div>
            </div>
        </div>

        <div class="mt-4 overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-zinc-500 dark:text-zinc-400 border-b border-zinc-200 dark:border-zinc-700">
                    <tr>
                        <th class="py-2 px-3 font-medium">{{ __('Date') }}</th>
                        <th class="py-2 px-3 font-medium">{{ __('Start') }}</th>
                        <th class="py-2 px-3 font-medium">{{ __('Patient') }}</th>
                        <th class="py-2 px-3 font-medium">{{ __('Procedure') }}</th>
                        <th class="py-2 px-3 font-medium text-right">{{ __('Amount') }}</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($this->pending as $p)
                        <tr>
                            <td class="py-3 px-3 text-zinc-600 dark:text-zinc-300 whitespace-nowrap">
                                {{ \Carbon\Carbon::parse($p->procedure_date)->format('d/m/Y') ?? '-' }}
                            </td>
                            <td class="py-3 px-3 text-zinc-600 dark:text-zinc-300 whitespace-nowrap">
                                {{ \Carbon\Carbon::parse($p->start_time)->format('H:i') ?? '-' }}
                            </td>
                            <td class="py-3 px-3 text-zinc-900 dark:text-zinc-100 font-medium whitespace-nowrap">
                                {{ $p->patient_name ?? '-' }}
                            </td>
                            <td class="py-3 px-3 text-zinc-600 dark:text-zinc-300 whitespace-nowrap">
                                <div class="flex items-center gap-2 whitespace-nowrap">
                                    {{ $p->procedure_type ?? '-' }}
                                    @if (Auth::user()->use_pay_scheme)
                                        <x-procedure-rule-badge :rule="data_get($p->pricing_snapshot, 'rule')"
                                            :videosurgery="$p->is_videosurgery" />
                                    @else
                                        @if ($p->is_videosurgery)
                                            <flux:badge color="indigo" size="sm">{{ __('Video') }}</flux:badge>
                                        @endif
                                    @endif
                                </div>
                            </td>
                            <td
                                class="py-3 px-3 text-right font-mono font-medium text-zinc-900 dark:text-zinc-100 whitespace-nowrap">
                                Q{{ number_format((float) ($p->calculated_amount ?? 0), 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-6 text-center text-zinc-500 dark:text-zinc-400">
                                {{ __('No pending procedures.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            @if($this->pending_count > count($this->pending))
                <p class="mt-3 text-xs text-zinc-500 dark:text-zinc-400">
                    {{ __('Showing latest') }} {{ count($this->pending) }}. {{ __('You have more pending items.') }}
                </p>
            @endif
        </div>
    </div>

    {{-- History --}}
    <div class="rounded-xl border bg-white p-6 dark:bg-zinc-900 dark:border-zinc-700">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ __('History') }}</h2>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('Paid vouchers.') }}
                </p>
            </div>

            <div class="flex items-end gap-3">
                <div>
                    <div class="text-xs text-zinc-500 dark:text-zinc-400 mb-1">{{ __('From') }}</div>
                    <input type="date" wire:model.live="from"
                        class="rounded-md border-zinc-300 dark:border-zinc-700 dark:bg-zinc-800 text-sm" />
                </div>
                <div>
                    <div class="text-xs text-zinc-500 dark:text-zinc-400 mb-1">{{ __('To') }}</div>
                    <input type="date" wire:model.live="to"
                        class="rounded-md border-zinc-300 dark:border-zinc-700 dark:bg-zinc-800 text-sm" />
                </div>
            </div>
        </div>

        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-left text-zinc-500 dark:text-zinc-400 border-b border-zinc-200 dark:border-zinc-700">
                    <tr>
                        <th class="py-2 pr-3 font-medium">
                            {{ __('Voucher') }}
                        </th>
                        <th class="py-2 pr-3 font-medium">
                            {{ __('Paid at') }}
                        </th>
                        <th class="py-2 pr-3 font-medium text-right">
                            {{ __('Procedures') }}
                        </th>
                        <th class="py-2 pr-3 font-medium text-right">
                            {{ __('Total') }}
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($this->batches as $b)
                        <tr>
                            <td class="py-3 pr-3 text-zinc-900 dark:text-zinc-100 font-mono">
                                QX-{{ str_pad((string) $b->id, 6, '0', STR_PAD_LEFT) }}
                            </td>
                            <td class="py-3 pr-3 text-zinc-600 dark:text-zinc-300">
                                {{ optional($b->paid_at)->format('Y-m-d H:i') ?? $b->paid_at }}
                            </td>
                            <td class="py-3 pr-3 text-right font-mono text-zinc-900 dark:text-zinc-100">
                                {{ (int) ($b->items_count ?? 0) }}
                            </td>
                            <td class="py-3 pr-3 text-right font-mono font-medium text-zinc-900 dark:text-zinc-100">
                                Q{{ number_format((float) ($b->total_amount ?? 0), 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-6 text-center text-zinc-500 dark:text-zinc-400">
                                {{ __('No payments yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>