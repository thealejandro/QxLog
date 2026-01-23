<?php

use App\Models\Procedure;
use App\Models\User;
use App\Services\PricingService;
use App\Support\TimeHelper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

use function Livewire\Volt\{state, computed, mount, rules};

state([
    // Form
    'procedure_date' => now()->toDateString(),
    'start_time' => '',
    'end_time' => '',
    'patient_name' => '',
    'procedure_type' => '',
    'is_videosurgery' => false,

    // Doctor: puede ser usuario o texto libre
    'doctor_id' => null,
    'doctor_query' => '',
    'doctor_suggestion' => [],
    // 'doctor_name' => '',

    // Circulating: puede ser usuario o texto libre
    'circulating_id' => null,
    'circulating_query' => '',
    'circulating_suggestion' => [],
    // 'circulating_name' => '',

    // Data para selects (cargada al iniciar)
    // 'doctors' => [],
    // 'circulatings' => [],

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
    'doctor_suggestion' => ['nullable', 'array'],

    'circulating_id' => ['nullable', 'integer', 'exists:users,id'],
    'circulating_query' => ['nullable', 'string', 'max:255'],
    'circulating_suggestion' => ['nullable', 'array'],
]);

mount(function () {
    // Guard simple mientras no hay middleware
    $user = Auth::user();
    if (!$user)
        abort(401);

    // Permitimos instrumentist y admin por ahora (para pruebas)
    if (!in_array($user->role, ['instrumentist', 'admin'], true)) {
        abort(403);
    }

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
    if (!$user)
        abort(401);

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
    $this->patient_name = '';
    $this->procedure_type = '';
    $this->start_time = '';
    $this->end_time = '';
    $this->is_videosurgery = false;

    $this->doctor_id = null;
    $this->doctor_name = '';
    $this->circulating_id = null;
    $this->circulating_name = '';

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

    $this->doctor_id = null; // si escribe, asumimos que puede cambiar selección

    $this->doctor_suggestions = User::query()
        ->where('role', 'doctor')
        ->where('name', 'like', "%{$q}%")
        ->orderBy('name')
        ->limit(8)
        ->get(['id', 'name'])
        ->map(fn($u) => ['id' => $u->id, 'name' => $u->name])
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

    $this->circulating_suggestions = User::query()
        ->where('role', 'circulating')
        ->where('name', 'like', "%{$q}%")
        ->orderBy('name')
        ->limit(8)
        ->get(['id', 'name'])
        ->map(fn($u) => ['id' => $u->id, 'name' => $u->name])
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


?>

<div class="max-w-4xl mx-auto p-4 md:p-6 pb-20">
    <div class="mb-6">
        <h1 class="text-2xl font-bold dark:text-white">Registrar procedimiento</h1>
        <p class="text-sm text-gray-600 dark:text-slate-400">QxLog • Registro quirúrgico (instrumentación)</p>
    </div>

    @if($success_message)
        <div
            class="mb-6 rounded-lg border border-green-200 bg-green-50 dark:bg-green-900/20 dark:border-green-800 px-4 py-3 text-green-800 dark:text-green-300">
            <div class="flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5">
                    <path fill-rule="evenodd"
                        d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z"
                        clip-rule="evenodd" />
                </svg>
                {{ $success_message }}
            </div>
        </div>
    @endif

    <div
        class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:bg-slate-800 dark:border-slate-700 space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Fecha</label>
                <input type="date"
                    class="w-full rounded-lg border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-gray-900 dark:text-white focus:border-teal-500 focus:ring-teal-500 shadow-sm"
                    wire:model.live="procedure_date">
                @error('procedure_date') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Hora inicio</label>
                <input type="time"
                    class="w-full rounded-lg border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-gray-900 dark:text-white focus:border-teal-500 focus:ring-teal-500 shadow-sm"
                    wire:model.live="start_time">
                @error('start_time') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Hora
                    finalización</label>
                <input type="time"
                    class="w-full rounded-lg border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-gray-900 dark:text-white focus:border-teal-500 focus:ring-teal-500 shadow-sm"
                    wire:model.live="end_time">
                @error('end_time') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Paciente</label>
                <input type="text"
                    class="w-full rounded-lg border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-slate-500 focus:border-teal-500 focus:ring-teal-500 shadow-sm"
                    placeholder="Nombre completo del paciente" wire:model="patient_name">
                @error('patient_name') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Tipo de
                    cirugía</label>
                <input type="text"
                    class="w-full rounded-lg border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-slate-500 focus:border-teal-500 focus:ring-teal-500 shadow-sm"
                    placeholder="Ej: Cesárea, Apendicectomía, Legrado..." wire:model="procedure_type">
                @error('procedure_type') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="flex items-center gap-3 py-2">
            <input type="checkbox"
                class="h-5 w-5 rounded border-gray-300 text-teal-600 focus:ring-teal-500 dark:border-slate-600 dark:bg-slate-900 dark:checked:bg-teal-500"
                wire:model="is_videosurgery" id="is_videosurgery">
            <label for="is_videosurgery"
                class="text-sm font-medium text-gray-700 dark:text-slate-300 select-none cursor-pointer">
                Videocirugía
                <span class="block text-xs font-normal text-gray-500 dark:text-slate-500">Marcar si el procedimiento fue
                    por video.</span>
            </label>
        </div>

        <div class="border-t border-gray-200 dark:border-slate-700 my-6"></div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="space-y-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">Médico (Cirujano)</label>

                <div class="relative">
                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-400">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5">
                            <path fill-rule="evenodd"
                                d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.452 4.391l3.328 3.329a.75.75 0 1 1-1.06 1.06l-3.329-3.328A7 7 0 0 1 2 9Z"
                                clip-rule="evenodd" />
                        </svg>
                    </div>
                    <input type="text"
                        class="w-full rounded-lg border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-slate-500 focus:border-teal-500 focus:ring-teal-500 pl-10 shadow-sm"
                        placeholder="Buscar o escribir nombre..." wire:model.live="doctor_query"
                        wire:input="searchDoctor">

                    @if(!empty($this->doctor_suggestions))
                        <div
                            class="absolute z-20 mt-1 w-full rounded-lg border border-gray-200 bg-white shadow-lg dark:bg-slate-800 dark:border-slate-700 overflow-hidden">
                            @foreach($this->doctor_suggestions as $s)
                                <button type="button"
                                    class="block w-full text-left px-4 py-2.5 hover:bg-teal-50 dark:hover:bg-slate-700 text-gray-700 dark:text-slate-200 transition-colors border-b border-gray-100 dark:border-slate-700/50 last:border-0"
                                    wire:click="selectDoctor({{ $s['id'] }})">
                                    {{ $s['name'] }}
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>

                @error('doctor_id') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                @error('doctor_query') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>

            <div class="space-y-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">Circulante
                    (Enfermería)</label>

                <div class="relative">
                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-400">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5">
                            <path fill-rule="evenodd"
                                d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.452 4.391l3.328 3.329a.75.75 0 1 1-1.06 1.06l-3.329-3.328A7 7 0 0 1 2 9Z"
                                clip-rule="evenodd" />
                        </svg>
                    </div>
                    <input type="text"
                        class="w-full rounded-lg border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-slate-500 focus:border-teal-500 focus:ring-teal-500 pl-10 shadow-sm"
                        placeholder="Buscar o escribir nombre..." wire:model.live="circulating_query"
                        wire:input="searchCirculating">

                    @if(!empty($this->circulating_suggestions))
                        <div
                            class="absolute z-20 mt-1 w-full rounded-lg border border-gray-200 bg-white shadow-lg dark:bg-slate-800 dark:border-slate-700 overflow-hidden">
                            @foreach($this->circulating_suggestions as $s)
                                <button type="button"
                                    class="block w-full text-left px-4 py-2.5 hover:bg-teal-50 dark:hover:bg-slate-700 text-gray-700 dark:text-slate-200 transition-colors border-b border-gray-100 dark:border-slate-700/50 last:border-0"
                                    wire:click="selectCirculating({{ $s['id'] }})">
                                    {{ $s['name'] }}
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>

                @error('circulating_id') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                @error('circulating_query') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="border-t border-gray-200 dark:border-slate-700 my-6"></div>

        <div
            class="flex flex-col sm:flex-row items-center justify-between gap-6 bg-gray-50 dark:bg-slate-900/50 p-4 rounded-lg border border-gray-100 dark:border-slate-700/50">
            <div class="flex items-center gap-8 w-full sm:w-auto">
                <div class="flex flex-col">
                    <span
                        class="text-xs font-medium text-gray-500 dark:text-slate-500 uppercase tracking-wider">Duración</span>
                    <span class="text-xl font-bold text-gray-900 dark:text-white">
                        {{ is_int($this->duration_minutes) ? $this->duration_minutes . ' min' : '--' }}
                    </span>
                </div>

                <div class="flex flex-col border-l border-gray-200 dark:border-slate-700 pl-8">
                    <span class="text-xs font-medium text-gray-500 dark:text-slate-500 uppercase tracking-wider">Monto
                        Estimado</span>
                    <span class="text-xl font-bold text-teal-600 dark:text-teal-400">
                        {{ is_numeric($this->amount_preview) ? 'Q' . number_format($this->amount_preview, 2) : '--' }}
                    </span>
                </div>
            </div>

            <button type="button" wire:click="save" wire:loading.attr="disabled"
                class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-lg bg-teal-600 px-6 py-2.5 text-white font-medium hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-lg shadow-teal-600/20">
                <span wire:loading.remove>Guardar Procedimiento</span>
                <span wire:loading class="flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none"
                        viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                        </circle>
                        <path class="opacity-75" fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                        </path>
                    </svg>
                    Guardando...
                </span>
            </button>
        </div>
    </div>

    <div class="mt-8">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Procedimientos Pendientes</h2>

            <div
                class="flex items-center gap-2 text-sm bg-white dark:bg-slate-800 px-3 py-1.5 rounded-full border border-gray-200 dark:border-slate-700 shadow-sm">
                <span class="text-gray-500 dark:text-slate-400">Total:</span>
                <span
                    class="font-bold text-teal-600 dark:text-teal-400">Q{{ number_format($this->pending_total ?? 0, 2) }}</span>
            </div>
        </div>

        <div
            class="rounded-xl border border-gray-200 bg-white shadow-sm dark:bg-slate-800 dark:border-slate-700 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm whitespace-nowrap">
                    <thead class="bg-gray-50 dark:bg-slate-700/50 text-gray-600 dark:text-slate-300">
                        <tr>
                            <th class="px-6 py-3 font-semibold">Fecha</th>
                            <th class="px-6 py-3 font-semibold">Horario</th>
                            <th class="px-6 py-3 font-semibold">Paciente</th>
                            <th class="px-6 py-3 font-semibold">Cirugía</th>
                            <th class="px-6 py-3 font-semibold text-right">Monto</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-slate-700">
                        @forelse($this->pending_procedures as $p)
                            <tr class="hover:bg-gray-50 dark:hover:bg-slate-700/30 transition-colors">
                                <td class="px-6 py-3 font-medium text-gray-900 dark:text-white">
                                    {{ $p->procedure_date->format('d/m/Y') }}
                                </td>
                                <td class="px-6 py-3 text-gray-600 dark:text-slate-400">
                                    {{ $p->start_time }} - {{ $p->end_time }}
                                    <span
                                        class="text-xs text-gray-400 dark:text-slate-500 ml-1">({{ $p->duration_minutes }}m)</span>
                                </td>
                                <td class="px-6 py-3 text-gray-700 dark:text-slate-300">{{ $p->patient_name }}</td>
                                <td class="px-6 py-3 text-gray-700 dark:text-slate-300">
                                    <div class="flex items-center gap-2">
                                        {{ $p->procedure_type }}
                                        @if($p->is_videosurgery)
                                            <span
                                                class="inline-flex items-center rounded-full bg-teal-50 dark:bg-teal-900/30 px-2 py-0.5 text-xs font-medium text-teal-700 dark:text-teal-300 ring-1 ring-inset ring-teal-600/20">Video</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-3 text-right font-bold text-teal-600 dark:text-teal-400">
                                    Q{{ number_format((float) $p->calculated_amount, 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-gray-500 dark:text-slate-400">
                                    <div class="flex flex-col items-center gap-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                            stroke-width="1.5" stroke="currentColor" class="size-6 opacity-50">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                        </svg>
                                        No tienes procedimientos pendientes todavía.
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>