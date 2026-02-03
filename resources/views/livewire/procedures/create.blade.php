<?php

use App\Models\Procedure;
use App\Models\User;
use App\Services\PricingService;
use App\Support\TimeHelper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

use function Livewire\Volt\{state, computed, mount, rules, updated};

state([
    // Form
    'procedure_date' => now()->toDateString(),
    'start_time' => now()->subHour()->format('H:i'),
    'end_time' => now()->format('H:i'),
    'patient_name' => '',
    'procedure_type' => '',
    'is_videosurgery' => false,

    // Doctor: puede ser usuario o texto libre
    'doctor_id' => null,
    'doctor_query' => '',
    'doctor_suggestions' => [],

    // Circulating: puede ser usuario o texto libre
    'circulating_id' => null,
    'circulating_query' => '',
    'circulating_suggestions' => [],

    // UX
    'success_message' => null,
]);

rules([
    'procedure_date' => ['required', 'date'],
    'start_time' => ['required', 'date_format:H:i'],
    'end_time' => ['required', 'date_format:H:i'],
    'patient_name' => ['required', 'string', 'max:255'],
    'procedure_type' => ['required', 'string', 'max:255'],
    'is_videosurgery' => ['boolean'],

    'doctor_id' => ['nullable', 'integer', 'exists:users,id'],
    'doctor_query' => ['nullable', 'string', 'max:255'],
    'doctor_suggestions' => ['nullable', 'array'],

    'circulating_id' => ['nullable', 'integer', 'exists:users,id'],
    'circulating_query' => ['nullable', 'string', 'max:255'],
    'circulating_suggestions' => ['nullable', 'array'],
]);

mount(function () {
    abort_unless((bool) Auth::check(), 401, 'Unauthorized');

    abort_unless(in_array(Auth::user()->role, ['instrumentist', 'admin'], true), 403, 'Unauthorized');

    // Cargar listas para selects (si existen)
    $this->doctors = User::query()
        ->where('role', 'doctor')
        ->orderBy('name')
        ->get(['id', 'name'])
        ->map(fn($u) => ['id' => $u->id, 'name' => $u->name])
        ->all();

    $this->circulatings = User::query()
        ->where('role', 'circulating')
        ->orderBy('name')
        ->get(['id', 'name'])
        ->map(fn($u) => ['id' => $u->id, 'name' => $u->name])
        ->all();
});

$duration_minutes = computed(function () {
    if (!$this->procedure_date || !$this->start_time || !$this->end_time) {
        return null;
    }

    try {
        $mins = TimeHelper::durationMinutes($this->procedure_date, $this->start_time, $this->end_time);
        return $mins;
    } catch (\Throwable $e) {
        return null;
    }
});

$amount_preview = computed(function () {
    $user = Auth::user();
    if (!$user)
        return null;

    if (!$this->start_time || !$this->end_time || !$this->procedure_date)
        return null;

    $mins = $this->duration_minutes;
    if (!is_int($mins) || $mins <= 0)
        return null;

    $pricing = app(PricingService::class)->calculate(
        instrumentist: $user,
        isVideosurgery: (bool) $this->is_videosurgery,
        durationMinutes: $mins,
        startTimeHHMM: $this->start_time,
        endTimeHHMM: $this->end_time,
    );

    return $pricing['amount'] ?? null;
});

$save = function () {
    $this->success_message = null;

    $user = Auth::user();
    abort_unless((bool) Auth::check(), 401, 'Unauthorized');

    // Validación base (rules())
    $data = $this->validate();

    // Validación "al menos uno": doctor_id o doctor_query
    $doctorId = $data['doctor_id'] ?? null;
    $doctorName = trim((string) ($data['doctor_query'] ?? ''));

    if (!$doctorId && $doctorName === '') {
        throw ValidationException::withMessages([
            'doctor_query' => 'Selecciona un médico o escribe el nombre.',
        ]);
    }

    // Circulante: al menos circulating_id o circulating_name
    $circulatingId = $data['circulating_id'] ?? null;
    $circulatingName = trim((string) ($data['circulating_query'] ?? ''));

    if (!$circulatingId && $circulatingName === '') {
        throw ValidationException::withMessages([
            'circulating_query' => 'Selecciona un circulante o escribe el nombre.',
        ]);
    }

    // Duración
    $durationMinutes = TimeHelper::durationMinutes($data['procedure_date'], $data['start_time'], $data['end_time']);

    // Protección básica: que no sea 0 y que no sea una locura
    if ($durationMinutes <= 0) {
        throw ValidationException::withMessages([
            'end_time' => 'La hora de finalización debe ser posterior a la hora de inicio.',
        ]);
    }

    if ($durationMinutes > (24 * 60)) {
        throw ValidationException::withMessages([
            'end_time' => 'La duración no puede superar 24 horas.',
        ]);
    }

    // Pricing
    $pricing = app(PricingService::class)->calculate(
        instrumentist: $user,
        isVideosurgery: (bool) $data['is_videosurgery'],
        durationMinutes: $durationMinutes,
        startTimeHHMM: $data['start_time'],
        endTimeHHMM: $data['end_time'],
    );

    DB::transaction(function () use ($user, $data, $doctorId, $doctorName, $circulatingId, $circulatingName, $durationMinutes, $pricing) {
        Procedure::create([
            'procedure_date' => $data['procedure_date'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'duration_minutes' => $durationMinutes,

            'patient_name' => $data['patient_name'],
            'procedure_type' => $data['procedure_type'],
            'is_videosurgery' => (bool) $data['is_videosurgery'],

            'instrumentist_id' => $user->id,
            'instrumentist_name' => $user->name, // snapshot

            'doctor_id' => $doctorId,
            'doctor_name' => $doctorId ? optional(User::find($doctorId))->name : $doctorName,

            'circulating_id' => $circulatingId,
            'circulating_name' => $circulatingId ? optional(User::find($circulatingId))->name : $circulatingName,

            'calculated_amount' => (float) ($pricing['amount'] ?? 0),
            'pricing_snapshot' => $pricing['snapshot'] ?? null,

            'status' => 'pending',
        ]);
    });

    // Reset parcial para facilidad en tablet
    $this->procedure_date = now()->toDateString();
    $this->patient_name = '';
    $this->procedure_type = '';
    $this->start_time = now()->subHour()->format('H:i');
    $this->end_time = now()->format('H:i');
    $this->is_videosurgery = false;

    $this->doctor_id = null;
    $this->doctor_name = '';
    $this->doctor_suggestions = [];

    $this->circulating_id = null;
    $this->circulating_name = '';
    $this->circulating_suggestions = [];

    $this->success_message = 'Procedimiento registrado (pendiente).';

    $this->dispatch('$refresh');
};

$pending_procedures = computed(function () {
    $user = Auth::user();
    if (!$user)
        return [];

    return Procedure::query()
        ->where('instrumentist_id', $user->id)
        ->where('status', 'pending')
        ->orderByDesc('procedure_date')
        ->orderByDesc('start_time')
        ->limit(50)
        ->get();
});

$pending_total = computed(function () {
    $user = Auth::user();
    if (!$user)
        return 0;

    return (float) Procedure::query()
        ->where('instrumentist_id', $user->id)
        ->where('status', 'pending')
        ->sum('calculated_amount');
});

$searchDoctor = function () {
    $q = trim((string) $this->doctor_query);

    // si ya seleccionó un doctor y el texto coincide, no spamear búsquedas
    if ($this->doctor_id && $q === '') {
        $this->doctor_suggestions = [];
        return;
    }

    // Si está vacío, no mostrar nada
    if ($q === '') {
        $this->doctor_suggestions = [];
        $this->doctor_id = null;
        return;
    }

    $this->doctor_id = null;

    // Búsqueda insensible a acentos en PHP (más robusto que depender de la collation de la DB)
    $allDoctors = User::query()
        ->where('role', 'doctor')
        ->orderBy('name')
        ->get(['id', 'name']);

    $normalizedQ = Str::ascii(Str::lower($q));

    $this->doctor_suggestions = $allDoctors
        ->filter(function ($u) use ($normalizedQ) {
            $normalizedName = Str::ascii(Str::lower($u->name));
            return str_contains($normalizedName, $normalizedQ);
        })
        ->take(8)
        ->values() // Re-indexar array para JSON
        ->all();
};

$selectDoctor = function (int $id) {
    $u = User::query()->where('role', 'doctor')->find($id);

    if (!$u)
        return;

    $this->doctor_id = $u->id;
    $this->doctor_query = $u->name;
    $this->doctor_suggestions = [];
};

$searchCirculating = function () {
    $q = trim((string) $this->circulating_query);

    if ($q === '') {
        $this->circulating_suggestions = [];
        $this->circulating_id = null;
        return;
    }

    $this->circulating_id = null;

    $allCirculatings = User::query()
        ->where('role', 'circulating')
        ->orderBy('name')
        ->get(['id', 'name']);

    $normalizedQ = Str::ascii(Str::lower($q));

    $this->circulating_suggestions = $allCirculatings
        ->filter(function ($u) use ($normalizedQ) {
            $normalizedName = Str::ascii(Str::lower($u->name));
            return str_contains($normalizedName, $normalizedQ);
        })
        ->take(8)
        ->values()
        ->all();
};

$selectCirculating = function (int $id) {
    $u = User::query()->where('role', 'circulating')->find($id);

    if (!$u)
        return;

    $this->circulating_id = $u->id;
    $this->circulating_query = $u->name;
    $this->circulating_suggestions = [];
};

updated(['doctor_query' => $searchDoctor, 'circulating_query' => $searchCirculating]);

?>

<div class="max-w-4xl mx-auto p-4 space-y-6">
    <div class="mb-4">
        <flux:heading size="xl">Registrar procedimiento</flux:heading>
        <flux:subheading>QxLog • Registro de intervenciones quirúrgicas • (Instrumentista)</flux:subheading>
    </div>

    @if($success_message)
        <div
            class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-800 dark:bg-green-900/30 dark:border-green-800 dark:text-green-300 flex items-center gap-2">
            <flux:icon.check-circle class="size-5" />
            {{ $success_message }}
        </div>
    @endif

    <div class="rounded-xl border bg-white p-6 dark:bg-zinc-900 dark:border-zinc-700 space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <flux:label>
                    {{ __('Fecha') }}
                    <flux:icon.calendar />
                </flux:label>
                <input type="date" max="{{ now()->format('Y-m-d') }}" min="{{ now()->subWeeks(2)->format('Y-m-d') }}"
                    wire:model.live="procedure_date"
                    class="mt-2 block w-full min-w-0 max-w-full rounded-lg border-zinc-200 bg-indigo-50 py-2.5 px-3 text-sm text-zinc-900 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-hidden dark:border-zinc-700 dark:bg-zinc-700 dark:text-zinc-100 dark:focus:border-indigo-400 hover:border-zinc-300 dark:hover:border-zinc-600 transition-colors" />

                @error('procedure_date') <p class="text-sm text-red-600 dark:text-red-400 mt-1">
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div>
                <flux:label>
                    {{ __('Hora de inicio') }}
                    <flux:icon.clock />
                </flux:label>
                <input type="time" wire:model.live="start_time"
                    class="mt-2 block w-full min-w-0 max-w-full rounded-lg border-zinc-200 bg-indigo-50 py-2.5 px-3 text-sm text-zinc-900 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-hidden dark:border-zinc-700 dark:bg-zinc-700 dark:text-zinc-100 dark:focus:border-indigo-400 hover:border-zinc-300 dark:hover:border-zinc-600 transition-colors" />
                @error('start_time') <p class="text-sm text-red-600 dark:text-red-400 mt-1">
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div>
                <flux:label>
                    {{ __('Hora de finalización') }}
                    <flux:icon.clock />
                </flux:label>
                <input type="time" wire:model.live="end_time"
                    class="mt-2 block w-full min-w-0 max-w-full rounded-lg border-zinc-200 bg-indigo-50 py-2.5 px-3 text-sm text-zinc-900 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-hidden dark:border-zinc-700 dark:bg-zinc-700 dark:text-zinc-100 dark:focus:border-indigo-400 hover:border-zinc-300 dark:hover:border-zinc-600 transition-colors" />
                @error('end_time') <p class="text-sm text-red-600 dark:text-red-400 mt-1">
                        {{ $message }}
                    </p>
                @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <flux:label>
                    {{ __('Paciente') }}
                    <flux:icon.user />
                </flux:label>
                <input type="text" wire:model="patient_name" placeholder="{{ __('Nombre del paciente') }}"
                    class="mt-2 block w-full rounded-lg border-zinc-200 bg-indigo-50 py-2.5 px-3 text-sm text-zinc-900 placeholder-zinc-400 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-hidden dark:border-zinc-700 dark:bg-zinc-700 dark:text-zinc-100 dark:focus:border-indigo-400 dark:placeholder-zinc-400 hover:border-zinc-300 dark:hover:border-zinc-600 transition-colors" />
                @error('patient_name') <p class="text-sm text-red-600 dark:text-red-400 mt-1">
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div>
                <flux:label>
                    {{ __('Procedimiento') }}
                </flux:label>
                <input type="text" wire:model="procedure_type" placeholder="{{ __('Cesárea, Apendicectomía...') }}"
                    class="mt-2 block w-full rounded-lg border-zinc-200 bg-indigo-50 py-2.5 px-3 text-sm text-zinc-900 placeholder-zinc-400 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-hidden dark:border-zinc-700 dark:bg-zinc-700 dark:text-zinc-100 dark:focus:border-indigo-400 dark:placeholder-zinc-400 hover:border-zinc-300 dark:hover:border-zinc-600 transition-colors" />
                @error('procedure_type') <p class="text-sm text-red-600 dark:text-red-400 mt-1">
                        {{ $message }}
                    </p>
                @enderror
            </div>
        </div>

        <div class="w-full flex justify-center md:justify-start">
            <flux:checkbox wire:model="is_videosurgery" label="{{ __('Videocirugía') }}"
                description="{{ __('Marcar si el procedimiento fue por video.') }}" />
        </div>

        <hr class="border-zinc-300 dark:border-zinc-600">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="space-y-2">
                <flux:label>
                    {{ __('Médico (Cirujano)') }}
                </flux:label>

                <div class="relative">
                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-zinc-400">
                        <flux:icon.magnifying-glass class="size-5" />
                    </div>
                    <input type="text"
                        class="block w-full rounded-lg border-zinc-200 bg-indigo-50/20 py-2.5 pl-10 pr-3 text-sm text-zinc-900 placeholder-zinc-400 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-hidden dark:border-zinc-700 dark:bg-zinc-800/50 dark:text-zinc-100 dark:focus:border-indigo-400 dark:placeholder-zinc-400 hover:border-zinc-300 dark:hover:border-zinc-600 transition-colors"
                        placeholder="{{ __('Buscar o escribir nombre...') }}"
                        wire:model.live.debounce.200ms="doctor_query">

                    @if(!empty($this->doctor_suggestions))
                        <div
                            class="absolute z-20 mt-1 w-full rounded-lg border border-zinc-200 bg-white shadow-lg dark:bg-zinc-700 dark:border-indigo-400 overflow-hidden">
                            @foreach($this->doctor_suggestions as $s)
                                <button type="button"
                                    class="block w-full text-left px-4 py-2.5 hover:bg-zinc-50 dark:hover:bg-indigo-400/50 text-zinc-700 dark:text-zinc-200 transition-colors border-b border-zinc-100 dark:border-indigo-400 last:border-0"
                                    wire:click="selectDoctor({{ $s['id'] }})">
                                    {{ $s['name'] }}
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>

                @error('doctor_id')
                    <p class="text-sm text-red-600 dark:text-red-400">
                        {{ $message }}
                    </p>
                @enderror

                @error('doctor_query')
                    <p class="text-sm text-red-600 dark:text-red-400">
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div class="space-y-2">
                <flux:label>
                    {{ __('Circulante (Enfermería)') }}
                </flux:label>

                <div class="relative">
                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-zinc-400">
                        <flux:icon.magnifying-glass class="size-5" />
                    </div>
                    <input type="text"
                        class="block w-full rounded-lg border-zinc-200 bg-indigo-50/20 py-2.5 pl-10 pr-3 text-sm text-zinc-900 placeholder-zinc-400 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-hidden dark:border-zinc-700 dark:bg-zinc-800/50 dark:text-zinc-100 dark:focus:border-indigo-400 dark:placeholder-zinc-400 hover:border-zinc-300 dark:hover:border-zinc-600 transition-colors"
                        placeholder="{{ __('Buscar o escribir nombre...') }}"
                        wire:model.live.debounce.200ms="circulating_query">

                    @if(!empty($this->circulating_suggestions))
                        <div
                            class="absolute z-20 mt-1 w-full rounded-lg border border-zinc-200 bg-white shadow-lg dark:bg-zinc-700 dark:border-indigo-400 overflow-hidden">
                            @foreach($this->circulating_suggestions as $s)
                                <button type="button"
                                    class="block w-full text-left px-4 py-2.5 hover:bg-zinc-50 dark:hover:bg-indigo-400/50 text-zinc-700 dark:text-zinc-200 transition-colors border-b border-zinc-100 dark:border-indigo-400/50 last:border-0"
                                    wire:click="selectCirculating({{ $s['id'] }})">
                                    {{ $s['name'] }}
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>

                @error('circulating_id')
                    <p class="text-sm text-red-600 dark:text-red-400">
                        {{ $message }}
                    </p>
                @enderror
                @error('circulating_query')
                    <p class="text-sm text-red-600 dark:text-red-400">
                        {{ $message }}
                    </p>
                @enderror
            </div>
        </div>

        <hr class="border-zinc-200 dark:border-zinc-700">

        <div
            class="flex flex-col sm:flex-row items-center justify-between gap-6 bg-zinc-50 dark:bg-zinc-800/50 p-4 rounded-lg border border-zinc-100 dark:border-zinc-700/50">
            <div class="flex items-center gap-8 w-full sm:w-auto">
                <div class="flex flex-col">
                    <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                        {{ __('Duración') }}
                    </span>
                    <span class="text-xl font-bold text-zinc-900 dark:text-zinc-100">
                        {{ is_int($this->duration_minutes) ? $this->duration_minutes . ' min' : '--' }}
                    </span>
                </div>

                <div class="flex flex-col border-l border-zinc-200 dark:border-zinc-700 pl-8">
                    <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                        {{ __('Monto') }}
                    </span>
                    <span class="text-xl font-bold text-emerald-600 dark:text-emerald-400">
                        {{ is_numeric($this->amount_preview) ? 'Q' . number_format($this->amount_preview, 2) : '--' }}
                    </span>
                </div>
            </div>

            <flux:button wire:click="save" variant="primary" loading="save" class="w-full sm:w-auto">
                {{ __('Registrar Procedimiento') }}
            </flux:button>
        </div>
    </div>

    <div class="mt-8 space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                {{ __('Procedimientos Pendientes') }}
            </h2>

            <div
                class="flex items-center gap-2 text-sm bg-white dark:bg-zinc-800 px-3 py-1.5 rounded-full border border-zinc-200 dark:border-zinc-700 shadow-sm">
                <span class="text-zinc-500 dark:text-zinc-400">
                    {{ __('Total') }}:
                </span>
                <span class="text-lg font-bold text-zinc-600 dark:text-zinc-200">
                    Q{{ number_format($this->pending_total ?? 0, 2) }}
                </span>
            </div>
        </div>

        <div
            class="rounded-xl border border-zinc-200 bg-white shadow-sm dark:bg-zinc-800 dark:border-zinc-700 overflow-hidden">
            {{-- Desktop --}}
            <div class="hidden md:block overflow-x-auto">
                <table class="min-w-full text-left text-sm whitespace-nowrap">
                    <thead class="bg-zinc-50 dark:bg-zinc-800 text-zinc-500 dark:text-zinc-400 text-center">
                        <tr>
                            <th class="px-6 py-3 font-medium">
                                <flux:label for="procedure_date">{{ __('Fecha') }}</flux:label>
                            </th>
                            <th class="px-6 py-3 font-medium">
                                <flux:label for="procedure_time">{{ __('Horario') }}</flux:label>
                            </th>
                            <th class="px-6 py-3 font-medium">
                                <flux:label for="patient_id">{{ __('Paciente') }}</flux:label>
                            </th>
                            <th class="px-6 py-3 font-medium">
                                <flux:label for="procedure_id">{{ __('Cirugía') }}</flux:label>
                            </th>
                            <th class="px-6 py-3 font-medium text-right">
                                <flux:label for="amount">{{ __('Monto') }}</flux:label>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700 whitespace-nowrap">
                        @forelse($this->pending_procedures as $p)
                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-700/30 transition-colors">
                                <td class="px-4 py-3 font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ $p->procedure_date->format('d/m/Y') }}
                                </td>
                                <td
                                    class="px-4 py-3 text-zinc-700 dark:text-zinc-300 whitespace-nowrap items-center text-center">
                                    {{ Carbon\Carbon::parse($p->start_time)->format('H:i') }}
                                    <span class="text-xs text-zinc-400 dark:text-zinc-500 ml-1">-</span>
                                    {{ Carbon\Carbon::parse($p->end_time)->format('H:i') }}
                                    <br>
                                    <span class="text-xs text-zinc-400 dark:text-zinc-500 ml-1">
                                        {{ $p->duration_minutes }} min
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">
                                    {{ $p->patient_name }}
                                </td>
                                <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">
                                    <div class="flex flex-row justify-between items-center gap-2">
                                        {{ $p->procedure_type }}
                                        @if(Auth::user()->use_pay_scheme)
                                            <x-procedure-rule-badge :rule="data_get($p, 'pricing_snapshot.rule')"
                                                :videosurgery="$p->is_videosurgery" />
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right font-bold text-emerald-600 dark:text-emerald-400">
                                    Q{{ number_format((float) $p->calculated_amount, 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-zinc-500 dark:text-zinc-400">
                                    <div class="flex flex-col items-center gap-2">
                                        <flux:icon.document-text class="size-6 opacity-50" />
                                        No tienes procedimientos pendientes todavía.
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Mobile --}}
            <div class="md:hidden divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse($this->pending_procedures as $p)
                    <div class="p-4 bg-white dark:bg-zinc-900 space-y-4">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <div class="font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ $p->patient_name }}
                                </div>
                                <div class="text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ $p->procedure_type }}
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

                        <div class="flex items-center justify-between text-sm pt-2 text-zinc-500 dark:text-zinc-400">
                            <div class="flex flex-col items-center">
                                <div class="flex justify-between items-center">
                                    <div class="flex items-center">
                                        {{ Carbon\Carbon::parse($p->start_time)->format('H:i') }} <span
                                            class="text-xs text-zinc-400 dark:text-zinc-500 ml-1">hrs</span>
                                    </div>

                                    <span class="text-xs text-zinc-400 dark:text-zinc-500 mx-1">-</span>

                                    <div class="flex items-center">
                                        {{ Carbon\Carbon::parse($p->end_time)->format('H:i') }} <span
                                            class="text-xs text-zinc-400 dark:text-zinc-500 ml-1">hrs</span>
                                    </div>
                                </div>

                                @if(Auth::user()->use_pay_scheme)
                                    <span class="text-xs text-zinc-400 dark:text-zinc-500 ml-1">
                                        {{ $p->duration_minutes }} min
                                    </span>
                                @endif
                            </div>
                            @if (Auth::user()->use_pay_scheme)
                                <x-procedure-rule-badge :rule="data_get($p, 'pricing_snapshot.rule')"
                                    :videosurgery="$p->is_videosurgery" />
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center text-zinc-500 dark:text-zinc-400">
                        No hay procedimientos pendientes todavía.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>