<?php

use App\Models\PayoutBatch;
use Illuminate\Support\Facades\Auth;

use function Livewire\Volt\{state, mount};

state([
    'batch' => null,
    'items' => [],
]);

mount(function (string|int $batch) {
    $user = Auth::user();
    if (!$user)
        abort(401);
    if ($user->role !== 'admin')
        abort(403);

    $b = PayoutBatch::query()
        ->with([
            'instrumentist:id,name',
            'paidByUser:id,name',
            'items.procedure',
        ])
        ->findOrFail($batch);

    $this->batch = $b;
    $this->items = $b->items;
});

?>

<style>
    @media print {
        .no-print {
            display: none !important;
        }

        .print-wrap {
            padding: 0 !important;
        }

        body {
            background: #fff !important;
            color: #000 !important;
        }

        .dark-mode-override {
            background-color: #fff !important;
            color: #000 !important;
            border-color: #e5e7eb !important;
        }
    }
</style>

<div class="max-w-4xl mx-auto p-4 print-wrap">
    <div class="no-print mb-6 flex items-center justify-between gap-2">
        <a href="{{ route('payouts.index') }}"
            class="text-sm text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-200 transition-colors flex items-center gap-1">
            <flux:icon.arrow-left size="xs" /> Volver
        </a>

        <div class="flex gap-2">
            <flux:button onclick="window.print()">
                <flux:icon.printer class="size-4 mr-2" />
                Imprimir
            </flux:button>
        </div>
    </div>

    <div class="rounded-xl border bg-white p-8 dark:bg-zinc-900 dark:border-zinc-700 dark-mode-override">
        <div class="flex items-start justify-between gap-6 pb-6 border-b border-zinc-200 dark:border-zinc-700">
            <div>
                <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">Voucher de Pago</h1>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">QxLog • Registro de cirugías instrumentadas</p>
            </div>

            <div class="text-right text-sm">
                <div><span class="text-zinc-500 dark:text-zinc-400">No.:</span> <span
                        class="font-semibold text-zinc-900 dark:text-zinc-100">#{{ $this->batch->id }}</span>
                </div>
                <div><span class="text-zinc-500 dark:text-zinc-400">Fecha pago:</span>
                    <span class="font-semibold text-zinc-900 dark:text-zinc-100">
                        {{ optional($this->batch->paid_at)->format('Y-m-d H:i') ?? $this->batch->paid_at }}
                    </span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm py-6 border-b border-zinc-200 dark:border-zinc-700">
            <div>
                <div class="text-zinc-500 dark:text-zinc-400 uppercase text-xs tracking-wider mb-1">Instrumentista</div>
                <div class="font-semibold text-lg text-zinc-900 dark:text-zinc-100">
                    {{ $this->batch->instrumentist->name ?? ('#' . $this->batch->instrumentist_id) }}
                </div>
            </div>

            <div class="md:text-right">
                <div class="text-zinc-500 dark:text-zinc-400 uppercase text-xs tracking-wider mb-1">Pagado por</div>
                <div class="font-semibold text-lg text-zinc-900 dark:text-zinc-100">
                    {{ $this->batch->paidByUser->name ?? ('#' . $this->batch->paid_by_user_id) }}
                </div>
            </div>
        </div>

        <div class="mt-6 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-left text-zinc-500 dark:text-zinc-400 border-b border-zinc-200 dark:border-zinc-700">
                    <tr>
                        <th class="py-2 pr-3 font-medium">Fecha</th>
                        <th class="py-2 pr-3 font-medium">Inicio</th>
                        <th class="py-2 pr-3 font-medium">Paciente</th>
                        <th class="py-2 pr-3 font-medium">Cirugía</th>
                        <th class="py-2 pr-3 font-medium text-right">Monto</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach($this->items as $it)
                        @php
                            $p = $it->procedure;
                        @endphp
                        <tr>
                            <td class="py-3 pr-3 text-zinc-600 dark:text-zinc-300">{{ $p->procedure_date ?? '-' }}</td>
                            <td class="py-3 pr-3 text-zinc-600 dark:text-zinc-300">{{ $p->start_time ?? '-' }}</td>
                            <td class="py-3 pr-3 text-zinc-900 dark:text-zinc-100 font-medium">{{ $p->patient_name ?? '-' }}
                            </td>
                            <td class="py-3 pr-3 text-zinc-600 dark:text-zinc-300">
                                {{ $p->procedure_type ?? '-' }}
                                @if(($p->is_videosurgery ?? false) === true)
                                    <span
                                        class="ml-2 inline-flex items-center rounded-full bg-zinc-100 dark:bg-zinc-800 px-2 py-0.5 text-xs font-medium text-zinc-800 dark:text-zinc-200">Video</span>
                                @endif
                            </td>
                            <td class="py-3 pr-3 text-right font-mono font-medium text-zinc-900 dark:text-zinc-100">
                                Q{{ number_format((float) $it->amount, 2) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>

                <tfoot>
                    <tr>
                        <td colspan="4" class="pt-6 text-right font-bold text-zinc-900 dark:text-zinc-100">Total:</td>
                        <td
                            class="pt-6 text-right font-bold text-xl text-zinc-900 dark:text-zinc-100 border-t border-zinc-200 dark:border-zinc-700 mt-6 block">
                            Q{{ number_format((float) $this->batch->total_amount, 2) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div
            class="grid grid-cols-1 md:grid-cols-2 gap-12 text-sm mt-12 pt-6 border-t border-zinc-200 dark:border-zinc-700">
            <div>
                <div class="text-zinc-500 dark:text-zinc-400 mb-12">Firma instrumentista</div>
                <div class="border-t border-zinc-300 dark:border-zinc-600 pt-2 text-zinc-900 dark:text-zinc-100">
                    Nombre: {{ $this->batch->instrumentist->name ?? '' }}
                </div>
            </div>

            <div>
                <div class="text-zinc-500 dark:text-zinc-400 mb-12 md:text-right">Firma administración</div>
                <div
                    class="border-t border-zinc-300 dark:border-zinc-600 pt-2 md:text-right text-zinc-900 dark:text-zinc-100">
                    Nombre: {{ $this->batch->paidByUser->name ?? '' }}
                </div>
            </div>
        </div>

        <p class="mt-8 text-xs text-zinc-400 dark:text-zinc-500 text-center">
            Documento generado por QxLog. Conservar para control interno.
        </p>
    </div>
</div>