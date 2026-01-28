<?php

use App\Models\Procedure;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

use function Livewire\Volt\{state, computed, mount};

state([
    'q' => '',
    'status' => 'pending', // pending|paid|all
    'instrumentist_id' => '',
    'date_from' => null,
    'date_to' => null,
]);

mount(function () {
    abort_unless(Auth::check(), 401);

    // Solo admin o superadmin
    $u = Auth::user();
    abort_unless($u->role === 'admin' || (bool) $u->is_super_admin, 403);

    // default dates: hoy - 30 días
    $this->date_to = now()->toDateString();
    $this->date_from = now()->subDays(30)->toDateString();
});

$instrumentists = computed(function () {
    return User::query()
        ->where('role', 'instrumentist')
        ->orderBy('name')
        ->get(['id', 'name']);
});

$procedures = computed(function () {
    $query = Procedure::query()
        ->with(['instrumentist:id,name']) // ajusta relación si tu modelo lo llama distinto
        ->orderByDesc('procedure_date')
        ->orderByDesc('id');

    if ($this->status !== 'all') {
        $query->where('status', $this->status);
    }

    if ($this->instrumentist_id) {
        $query->where('instrumentist_id', (int) $this->instrumentist_id);
    }

    if ($this->date_from) {
        $query->whereDate('procedure_date', '>=', $this->date_from);
    }

    if ($this->date_to) {
        $query->whereDate('procedure_date', '<=', $this->date_to);
    }

    if ($this->q) {
        $term = trim($this->q);
        $query->where(function ($s) use ($term) {
            $s->where('patient_name', 'like', "%{$term}%")
                ->orWhere('procedure_type', 'like', "%{$term}%")
                ->orWhere('doctor_name', 'like', "%{$term}%")
                ->orWhere('circulating_name', 'like', "%{$term}%");
        });
    }

    return $query->limit(300)->get();
});

$total = computed(function () {
    return $this->procedures->sum(fn($p) => (float) $p->calculated_amount);
});

$ruleLabel = function (?string $rule) {
    return match ($rule) {
        'video_rate' => 'Video',
        'night_rate' => 'Madrugada',
        'long_case_rate' => 'Largo',
        default => 'Base',
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

<div class="max-w-7xl mx-auto p-4 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Procedimientos') }}</flux:heading>
            <flux:subheading>{{ __('Vista de administración') }}</flux:subheading>
        </div>
        <flux:button href="{{ route('procedures.create') }}" icon="plus"
            class="w-full sm:w-auto !bg-indigo-500 hover:!bg-indigo-600 !border-indigo-500 !text-white dark:!bg-indigo-600 dark:hover:!bg-indigo-500">
            {{ __('Registrar') }}
        </flux:button>
    </div>

    <div class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
            <div class="md:col-span-2">
                <flux:input icon="magnifying-glass" wire:model.live="q"
                    placeholder="Paciente, tipo, médico, circulante..." />
            </div>

            <div>
                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Estado</label>
                <select wire:model.change="status"
                    class="w-full rounded-lg border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900/50 text-zinc-900 dark:text-zinc-100 focus:ring-0 focus:border-zinc-500 p-2.5">
                    <option value="pending">Pendiente</option>
                    <option value="paid">Pagado</option>
                    <option value="all">Todos</option>
                </select>
            </div>

            <div class="md:col-span-1">
                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Instrumentista</label>
                <select wire:model.change="instrumentist_id"
                    class="w-full rounded-lg border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900/50 text-zinc-900 dark:text-zinc-100 focus:ring-0 focus:border-zinc-500 p-2.5">
                    <option value="">-- Todos --</option>
                    @foreach($this->instrumentists as $i)
                        <option value="{{ $i->id }}">{{ $i->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Desde</label>
                <input type="date" wire:model.change="date_from"
                    class="w-full rounded-lg border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900/50 text-zinc-900 dark:text-zinc-100 focus:ring-0 focus:border-zinc-500 p-2 text-sm">
            </div>

            <div>
                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Hasta</label>
                <input type="date" wire:model.change="date_to"
                    class="w-full rounded-lg border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900/50 text-zinc-900 dark:text-zinc-100 focus:ring-0 focus:border-zinc-500 p-2 text-sm">
            </div>
        </div>

        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between text-sm gap-2">
            <div class="text-zinc-600 dark:text-zinc-400">
                Mostrando
                <span class="font-medium text-zinc-900 dark:text-zinc-100 mx-1">
                    {{ $this->procedures->count() }}
                </span>
                (máx 300)
            </div>
            <div class="font-semibold text-zinc-900 dark:text-zinc-100">
                Total: Q{{ number_format($this->total, 2) }}
            </div>
        </div>

        <!-- Mobile View (Cards) -->
        <div class="grid grid-cols-1 gap-4 sm:hidden">
            @forelse($this->procedures as $p)
                @php
                    $rule = data_get($p->pricing_snapshot, 'rule', 'default_rate');
                @endphp
                <div
                    class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 shadow-sm space-y-3">
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-500">{{ $p->procedure_date?->format('d/m/Y') }}
                            </div>
                            <div class="font-medium text-zinc-900 dark:text-zinc-100 text-lg">{{ $p->patient_name }}</div>
                            <div class="text-xs text-zinc-400 font-mono">{{ $p->procedure_type }}</div>
                        </div>
                        <flux:badge size="sm" color="{{ $p->status === 'paid' ? 'green' : 'zinc' }}">
                            {{ $p->status === 'paid' ? 'Pagado' : 'Pendiente' }}
                        </flux:badge>
                    </div>

                    <div class="space-y-1 text-sm text-zinc-600 dark:text-zinc-400">
                        <div class="flex justify-between">
                            <span>Instrumentista:</span>
                            <span
                                class="font-medium text-zinc-900 dark:text-zinc-200">{{ $p->instrumentist->name ?? '—' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Horario:</span>
                            <span>{{ $p->start_time }} – {{ $p->end_time }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Duración:</span>
                            <span>{{ $p->duration_minutes ?? data_get($p->pricing_snapshot, 'duration_minutes', '—') }}
                                min</span>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-zinc-100 dark:border-zinc-800 flex justify-between items-center">
                        <flux:badge size="sm" color="{{ $ruleColor($rule) }}">{{ $ruleLabel($rule) }}</flux:badge>
                        <span
                            class="font-bold text-zinc-900 dark:text-zinc-100">Q{{ number_format((float) $p->calculated_amount, 2) }}</span>
                    </div>
                </div>
            @empty
                <div
                    class="p-8 text-center text-zinc-500 dark:text-zinc-400 italic bg-zinc-50 dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800">
                    {{ __('Sin resultados.') }}
                </div>
            @endforelse
        </div>

        <!-- Desktop View (Table) -->
        <div class="hidden sm:block overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-800/50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 uppercase tracking-wider">
                            {{ __('Fecha') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 uppercase tracking-wider">
                            {{ __('Paciente') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 uppercase tracking-wider">
                            {{ __('Tipo') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 uppercase tracking-wider">
                            {{ __('Instrumentista') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 uppercase tracking-wider">
                            {{ __('Horario') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 uppercase tracking-wider">
                            {{ __('Duración') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 uppercase tracking-wider">
                            {{ __('Regla') }}
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-zinc-500 uppercase tracking-wider">
                            {{ __('Monto') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 uppercase tracking-wider">
                            {{ __('Estado') }}
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-zinc-900 divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($this->procedures as $p)
                        @php
                            $rule = data_get($p->pricing_snapshot, 'rule', 'default_rate');
                        @endphp
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-zinc-500 dark:text-zinc-400">
                                {{ $p->procedure_date?->format('Y-m-d') ?? $p->procedure_date }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $p->patient_name }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-zinc-500 dark:text-zinc-400">
                                {{ $p->procedure_type }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-zinc-500 dark:text-zinc-400">
                                {{ $p->instrumentist->name ?? '—' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-zinc-500 dark:text-zinc-400">
                                {{ $p->start_time }} – {{ $p->end_time }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-zinc-500 dark:text-zinc-400">
                                {{ $p->duration_minutes ?? data_get($p->pricing_snapshot, 'duration_minutes', '—') }} min
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <flux:badge size="sm" color="{{ $ruleColor($rule) }}">{{ $ruleLabel($rule) }}</flux:badge>
                            </td>
                            <td
                                class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                Q{{ number_format((float) $p->calculated_amount, 2) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <flux:badge size="sm" color="{{ $p->status === 'paid' ? 'green' : 'zinc' }}">
                                    {{ $p->status === 'paid' ? 'Pagado' : 'Pendiente' }}
                                </flux:badge>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-6 py-8 text-center text-sm text-zinc-500 dark:text-zinc-400 italic">
                                {{ __('Sin resultados.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-4 text-xs text-zinc-500 dark:text-zinc-400 text-center sm:text-left">
            {{ __('Tip: si querés ver solo “lo de hoy”, poné Desde = Hoy y Hasta = Hoy.') }}
        </div>
    </div>
</div>