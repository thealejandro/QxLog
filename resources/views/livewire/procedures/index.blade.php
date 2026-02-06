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

    abort_unless((bool) Auth::user()->role !== 'admin', 403);

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

<div class="max-w-6xl mx-auto p-4 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">
                {{ __('Procedimientos') }}
            </flux:heading>
            <flux:subheading>
                {{ __('Vista de administración') }}
            </flux:subheading>
        </div>
    </div>

    <div class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
            <div class="md:col-span-2 items-center">
                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">
                    {{ __('Buscar') }}
                </label>
                <flux:input icon="magnifying-glass" wire:model.live="q"
                    placeholder="{{ __('Paciente, tipo, médico, circulante...') }}" />
            </div>

            <div class="md:col-span-1 items-center">
                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">
                    {{ __('Estado') }}
                </label>
                <select wire:model.live.change="status"
                    class="w-full rounded-lg border-zinc-200 bg-indigo-50 dark:border-zinc-600 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 p-2.5 hover:border-zinc-300 dark:hover:border-zinc-600 transition-colors">
                    <option value="pending">
                        {{ __('Pendiente') }}
                    </option>
                    <option value="paid">
                        {{ __('Pagado') }}
                    </option>
                    <option value="all">
                        {{ __('Todos') }}
                    </option>
                </select>
            </div>

            <div class="md:col-span-1 items-center">
                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">
                    {{ __('Instrumentista') }}
                </label>
                <select wire:model.live.change="instrumentist_id"
                    class="w-full rounded-lg border-zinc-200 bg-indigo-50 dark:border-zinc-600 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 p-2.5 hover:border-zinc-300 dark:hover:border-zinc-600 transition-colors">
                    <option value="">-- {{ __('Todos') }} --</option>
                    @foreach($this->instrumentists as $i)
                        <option value="{{ $i->id }}">{{ $i->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="md:col-span-1 items-center">
                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">
                    {{ __('Desde') }}
                </label>
                <input type="date" wire:model.live.change="date_from"
                    class="w-full rounded-lg border-zinc-200 bg-indigo-50 dark:border-zinc-600 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 p-2 text-sm hover:border-zinc-300 dark:hover:border-zinc-600 transition-colors">
            </div>

            <div class="md:col-span-1 items-center">
                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">
                    {{ __('Hasta') }}
                </label>
                <input type="date" wire:model.live.change="date_to"
                    class="w-full rounded-lg border-zinc-200 bg-indigo-50 dark:border-zinc-600 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 p-2 text-sm hover:border-zinc-300 dark:hover:border-zinc-600 transition-colors">
            </div>
        </div>

        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between text-sm gap-2">
            <div class="text-zinc-600 dark:text-zinc-400">
                {{ __('Mostrando') }}
                <span class="font-medium text-zinc-900 dark:text-zinc-100 mx-1">
                    {{ $this->procedures->count() }}
                </span>
                {{ __('máx 300') }}
            </div>
            <div class="font-semibold text-emerald-600 dark:text-emerald-400">
                {{ __('Total') }}: Q{{ number_format($this->total, 2) }}
            </div>
        </div>

        <!-- Mobile View (Cards) -->
        <div class="grid grid-cols-1 gap-4 sm:hidden">
            @forelse($this->procedures as $p)
                @php
                    $rule = data_get($p->pricing_snapshot, 'rule', 'default_rate');
                @endphp
                <div
                    class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-600 bg-white dark:bg-zinc-900 shadow-sm space-y-3">
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="font-medium text-zinc-900 dark:text-zinc-100 text-lg">
                                {{ $p->patient_name }}
                            </div>
                            <div class="text-xs text-zinc-400 font-mono">
                                {{ $p->procedure_type }}
                            </div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-500">
                                {{ $p->procedure_date?->format('d/m/Y') }}
                            </div>
                        </div>
                        <flux:badge size="sm" color="{{ $p->status === 'paid' ? 'green' : 'amber' }}">
                            {{ $p->status }}
                        </flux:badge>
                    </div>

                    <div class="space-y-1 text-sm text-zinc-500 dark:text-zinc-400">
                        <div class="flex justify-between">
                            <span class="font-medium">
                                {{ __('Instrumentista') }}:
                            </span>
                            <span class="font-medium">
                                {{ $p->instrumentist->name ?? '—' }}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="font-medium">
                                {{ __('Inicio - Fin') }}:
                            </span>
                            <span class="font-medium">
                                {{ \Carbon\Carbon::parse($p->start_time)->format('H:i') }}
                                <span>-</span>
                                {{ \Carbon\Carbon::parse($p->end_time)->format('H:i') }}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="font-medium">
                                {{ __('Duración') }}:
                            </span>
                            <span class="font-medium">
                                {{ $p->duration_minutes ?? '-' }}
                                <span>
                                    {{ __('min') }}
                                </span>
                            </span>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-zinc-200 dark:border-zinc-700 flex justify-between items-center">
                        <x-procedure-rule-badge :rule="$rule" :videosurgery="$p->is_videosurgery" />
                        <span class="font-bold text-emerald-600 dark:text-emerald-400">
                            Q{{ number_format((float) $p->calculated_amount, 2) }}
                        </span>
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
        <div class="hidden sm:block overflow-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700 text-zinc-500 dark:text-zinc-400">
                <thead class="bg-zinc-50 dark:bg-zinc-800 text-center">
                    <tr>
                        <th class="px-4 py-3 font-medium uppercase tracking-wider">
                            <flux:label>
                                {{ __('Fecha') }}
                            </flux:label>
                        </th>
                        <th class="px-4 py-3 font-medium uppercase tracking-wider">
                            <flux:label>
                                {{ __('Paciente') }}
                            </flux:label>
                        </th>
                        <th class="px-4 py-3 font-medium uppercase tracking-wider">
                            <flux:label>
                                {{ __('Procedimiento') }}
                            </flux:label>
                        </th>
                        <th class="px-4 py-3 font-medium uppercase tracking-wider">
                            <flux:label>
                                {{ __('Instrumentista') }}
                            </flux:label>
                        </th>
                        <th class="px-4 py-3 font-medium uppercase tracking-wider">
                            <div class="flex flex-row justify-between gap-1  items-center">
                                <div class="text-center">
                                    <flux:label>
                                        {{ __('Duración') }}
                                    </flux:label>
                                    <br>
                                    <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ __('Inicio - Fin') }}
                                    </span>
                                </div>
                                <div>
                                    <flux:badge size="sm" color="indigo">
                                        {{ __('Rules') }}
                                    </flux:badge>
                                </div>
                            </div>
                        </th>
                        <th class="px-4 py-3 font-medium uppercase tracking-wider">
                            <flux:label>
                                {{ __('Monto') }}
                            </flux:label>
                        </th>
                        <th class="px-4 py-3 font-medium uppercase tracking-wider">
                            <flux:label>
                                {{ __('Estado') }}
                            </flux:label>
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-zinc-900 divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($this->procedures as $p)
                        @php
                            $rule = data_get($p->pricing_snapshot, 'rule', 'default_rate');
                        @endphp
                        <tr
                            class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors text-zinc-600 dark:text-zinc-300">
                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                {{ $p->procedure_date->format('d/m/Y') }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $p->patient_name }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                {{ $p->procedure_type }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                {{ $p->instrumentist->name ?? '—' }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-center">
                                <div class="flex flex-row justify-between items-center">
                                    <div>
                                        {{ $p->duration_minutes }}
                                        <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ __('min') }}
                                        </span>
                                        <br>
                                        <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ \Carbon\Carbon::parse($p->start_time)->format('H:i') }}
                                            <span>
                                                -
                                            </span>
                                            {{ \Carbon\Carbon::parse($p->end_time)->format('H:i') }}
                                        </span>
                                    </div>
                                    <div>
                                        <x-procedure-rule-badge :rule="$rule" :videosurgery="$p->is_videosurgery" />
                                    </div>
                                </div>
                            </td>
                            <td
                                class="px-4 py-3 whitespace-nowrap text-right text-sm font-bold text-emerald-600 dark:text-emerald-400">
                                Q{{ number_format((float) $p->calculated_amount, 2) }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
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
    </div>
</div>