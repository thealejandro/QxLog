<?php

use App\Models\Procedure;
use App\Models\User;
use App\Services\PricingService;
use App\Support\TimeHelper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

use function Livewire\Volt\{state, mount, updated};

state([
    'procedure' => null,

    // Editable fields
    'procedure_date' => null,
    'start_time' => '',
    'end_time' => '',
    'patient_name' => '',
    'procedure_type' => '',
    'is_videosurgery' => false,

    'doctor_id' => null,
    'doctor_query' => '',
    'doctor_suggestions' => [],

    'circulating_id' => null,
    'circulating_query' => '',
    'circulating_suggestions' => [],

    'success_message' => null,
    'duration_minutes' => 0,
    'amount_preview' => 0,
]);

mount(function (Procedure $procedure) {
    $user = Auth::user();
    abort_unless($user && $user->can('procedures.edit'), 403);

    // Solo editable si pendiente
    abort_unless($procedure->status === 'pending', 403);

    $this->procedure = $procedure;

    $this->procedure_date = Carbon\Carbon::parse($procedure->procedure_date)->format('Y-m-d');
    $this->start_time = $procedure->start_time ? substr($procedure->start_time, 0, 5) : '';
    $this->end_time = $procedure->end_time ? substr($procedure->end_time, 0, 5) : '';
    $this->patient_name = $procedure->patient_name ?? '';
    $this->procedure_type = $procedure->procedure_type ?? '';
    $this->is_videosurgery = (bool) $procedure->is_videosurgery;
    $this->duration_minutes = $procedure->duration_minutes;
    $this->amount_preview = $procedure->calculated_amount;

    $this->doctor_id = $procedure->doctor_id;
    $this->doctor_query = $procedure->doctor_name ?? '';

    $this->circulating_id = $procedure->circulating_id;
    $this->circulating_query = $procedure->circulating_name ?? '';
});

$calculateDurationAndPrice = function () {
    if (!$this->procedure_date || !$this->start_time || !$this->end_time) {
        $this->duration_minutes = 0;
        $this->amount_preview = 0;
        return;
    }

    try {
        $mins = TimeHelper::durationMinutes($this->procedure_date, $this->start_time, $this->end_time);
        $this->duration_minutes = $mins > 0 ? $mins : 0;
    } catch (\Throwable $e) {
        $this->duration_minutes = 0;
    }

    $user = $this->procedure->instrumentist ?? User::find($this->procedure->instrumentist_id);

    if ($this->duration_minutes > 0 && $user) {
        $pricing = app(PricingService::class)->calculate(
            instrumentist: $user,
            isVideosurgery: (bool) $this->is_videosurgery,
            durationMinutes: $this->duration_minutes,
            startTimeHHMM: $this->start_time,
            endTimeHHMM: $this->end_time,
        );
        $this->amount_preview = $pricing['amount'] ?? 0;
    } else {
        $this->amount_preview = 0;
    }
};

$searchDoctor = function () {
    $q = trim((string) $this->doctor_query);

    if ($this->doctor_id && $q === '') {
        $this->doctor_suggestions = [];
        return;
    }

    if ($q === '') {
        $this->doctor_suggestions = [];
        $this->doctor_id = null;
        return;
    }

    $this->doctor_id = null;

    $allDoctors = User::role('doctor')
        ->orderBy('name')
        ->get(['id', 'name']);

    $normalizedQ = Str::ascii(Str::lower($q));

    $this->doctor_suggestions = $allDoctors
        ->filter(function ($u) use ($normalizedQ) {
            $normalizedName = Str::ascii(Str::lower($u->name));
            return str_contains($normalizedName, $normalizedQ);
        })
        ->take(8)
        ->values()
        ->all();
};

$selectDoctor = function (int $id) {
    $u = User::role('doctor')->find($id);

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

    $allCirculatings = User::role('circulating')
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
    $u = User::role('circulating')->find($id);

    if (!$u)
        return;

    $this->circulating_id = $u->id;
    $this->circulating_query = $u->name;
    $this->circulating_suggestions = [];
};

updated([
    'doctor_query' => $searchDoctor,
    'circulating_query' => $searchCirculating,
    'start_time' => $calculateDurationAndPrice,
    'end_time' => $calculateDurationAndPrice,
    'procedure_date' => $calculateDurationAndPrice,
    'is_videosurgery' => $calculateDurationAndPrice,
]);

$save = function () {
    $user = Auth::user();
    abort_unless($user && $user->can('procedures.edit'), 403);

    $p = $this->procedure;
    abort_unless($p && $p->exists, 404);
    abort_unless($p->status === 'pending', 403);

    if (!$this->procedure_date) {
        throw ValidationException::withMessages(['procedure_date' => 'La fecha es requerida.']);
    }
    if (!$this->start_time || !$this->end_time) {
        throw ValidationException::withMessages(['start_time' => 'Hora inicio y fin son requeridas.']);
    }
    if (trim((string) $this->patient_name) === '') {
        throw ValidationException::withMessages(['patient_name' => 'El paciente es requerido.']);
    }
    if (trim((string) $this->procedure_type) === '') {
        throw ValidationException::withMessages(['procedure_type' => 'El tipo de cirugía es requerido.']);
    }

    $doctorId = $this->doctor_id;
    $doctorName = trim((string) $this->doctor_query);
    if (!$doctorId && $doctorName === '') {
        throw ValidationException::withMessages(['doctor_query' => 'El médico es requerido.']);
    }

    $circulatingId = $this->circulating_id;
    $circulatingName = trim((string) $this->circulating_query);
    if (!$circulatingId && $circulatingName === '') {
        throw ValidationException::withMessages(['circulating_query' => 'El circulante es requerido.']);
    }

    $duration = TimeHelper::durationMinutes($this->procedure_date, $this->start_time, $this->end_time);
    if ($duration <= 0) {
        throw ValidationException::withMessages(['end_time' => 'La hora fin debe ser mayor a la hora inicio.']);
    }

    $instrumentist = $p->instrumentist ?? User::find($p->instrumentist_id);
    if (!$instrumentist) {
        throw ValidationException::withMessages(['instrumentist' => 'No se encontró el instrumentista.']);
    }

    $calc = app(PricingService::class)->calculate(
        $instrumentist,
        (bool) $this->is_videosurgery,
        (int) $duration,
        (string) $this->start_time,
        (string) $this->end_time,
    );

    DB::transaction(function () use ($p, $duration, $calc, $user, $doctorId, $doctorName, $circulatingId, $circulatingName) {
        $p->procedure_date = $this->procedure_date;
        $p->start_time = $this->start_time;
        $p->end_time = $this->end_time;
        $p->duration_minutes = (int) $duration;

        $p->patient_name = $this->patient_name;
        $p->procedure_type = $this->procedure_type;
        $p->is_videosurgery = (bool) $this->is_videosurgery;

        $p->doctor_id = $doctorId;
        $p->doctor_name = $doctorId ? optional(User::find($doctorId))->name : $doctorName;
        $p->circulating_id = $circulatingId;
        $p->circulating_name = $circulatingId ? optional(User::find($circulatingId))->name : $circulatingName;

        $p->calculated_amount = (float) $calc['amount'];
        $p->pricing_snapshot = $calc['snapshot'];

        if (Schema::hasColumn('procedures', 'edited_by_id')) {
            $p->edited_by_id = $user->id;
        }
        if (Schema::hasColumn('procedures', 'edited_at')) {
            $p->edited_at = now();
        }

        $p->save();
    });

    $this->success_message = 'Procedimiento actualizado y recalculado.';
};

?>

<div class="max-w-6xl mx-auto p-4 space-y-6">
    <flux:button href="{{ route('procedures.index') }}" icon="arrow-left" variant="subtle">{{ __('Back') }}
    </flux:button>
    <div>
        <flux:heading size="xl">{{ __('Edit Procedure') }}</flux:heading>
        <flux:subheading>{{ __('Modify the details of the surgical procedure.') }}</flux:subheading>
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
            <flux:field>
                <flux:label>
                    {{ __('Date') }}
                </flux:label>
                <flux:input type="date" max="{{ now()->format('Y-m-d') }}"
                    min="{{ now()->subWeeks(4)->format('Y-m-d') }}" wire:model.live="procedure_date" clearable />

                @error('procedure_date') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p>
                @enderror
            </flux:field>

            <flux:field>
                <flux:label>
                    {{ __('Start Time') }}
                </flux:label>
                <flux:input type="time" wire:model.live="start_time" clearable />
                @error('start_time') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
            </flux:field>

            <flux:field>
                <flux:label>
                    {{ __('End Time') }}
                </flux:label>
                <flux:input type="time" wire:model.live="end_time" clearable />
                @error('end_time') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
            </flux:field>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <flux:field>
                <flux:label>
                    {{ __('Patient') }}
                </flux:label>
                <flux:input type="text" wire:model="patient_name" placeholder="{{ __('Patient Name') }}" clearable />
                @error('patient_name') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p>
                @enderror
            </flux:field>

            <flux:field>
                <flux:label>
                    {{ __('Procedure') }}
                </flux:label>
                <flux:input type="text" wire:model="procedure_type" clearable
                    placeholder="{{ __('C-Section, Appendectomy...') }}" />
                @error('procedure_type') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p>
                @enderror
            </flux:field>
        </div>

        <div class="w-full flex justify-center md:justify-start">
            <flux:checkbox wire:model.live="is_videosurgery" label="{{ __('Videosurgery') }}"
                description="{{ __('Check if the procedure was by video.') }}" />
        </div>

        <hr class="border-indigo-300 dark:border-zinc-600">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="space-y-2">
                <flux:label>
                    {{ __('Doctor (Surgeon)') }}
                </flux:label>

                <div class="relative">
                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-zinc-400">
                        <flux:icon.magnifying-glass class="size-5" />
                    </div>
                    <input type="text"
                        class="mt-2 block w-full rounded-lg border-zinc-200 bg-indigo-50 py-2.5 pl-10 pr-3 text-sm text-zinc-900 placeholder-zinc-400 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-hidden dark:border-zinc-700 dark:bg-zinc-700 dark:text-zinc-100 dark:focus:border-indigo-400 dark:placeholder-zinc-400 hover:border-zinc-300 dark:hover:border-zinc-600 transition-colors"
                        placeholder="{{ __('Search or type name...') }}" wire:model.live.debounce.200ms="doctor_query">

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
                    {{ __('Circulating (Nurse)') }}
                </flux:label>

                <div class="relative">
                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-zinc-400">
                        <flux:icon.magnifying-glass class="size-5" />
                    </div>
                    <input type="text"
                        class="mt-2 block w-full rounded-lg border-zinc-200 bg-indigo-50 py-2.5 pl-10 pr-3 text-sm text-zinc-900 placeholder-zinc-400 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-hidden dark:border-zinc-700 dark:bg-zinc-700 dark:text-zinc-100 dark:focus:border-indigo-400 dark:placeholder-zinc-400 hover:border-zinc-300 dark:hover:border-zinc-600 transition-colors"
                        placeholder="{{ __('Search or type name...') }}"
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

        <hr class="border-indigo-300 dark:border-zinc-600">

        <div
            class="flex flex-col sm:flex-row items-center justify-between gap-6 bg-indigo-100 dark:bg-indigo-900/40 p-4 rounded-lg border border-indigo-100 dark:border-indigo-700/50">
            <div class="flex flex-row items-center justify-center gap-8 w-full sm:w-auto">
                <div class="flex flex-col items-start">
                    <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                        {{ __('Duration') }}
                    </span>
                    <span class="text-xl font-bold text-zinc-900 dark:text-zinc-100">
                        {{ is_int($this->duration_minutes) ? $this->duration_minutes . ' min' : '--' }}
                    </span>
                </div>
                <div class="w-px h-12 bg-indigo-300 dark:bg-indigo-600"></div>

                <div class="flex flex-col items-start">
                    <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                        {{ __('Amount') }}
                    </span>
                    <span class="text-xl font-bold text-indigo-600 dark:text-zinc-100">
                        {{ is_numeric($this->amount_preview) ? 'Q' . number_format($this->amount_preview, 2) : '--' }}
                    </span>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-4 w-full sm:w-auto">
                <flux:button href="{{ route('procedures.index') }}" variant="subtle" class="w-full sm:w-auto">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button wire:click="save" variant="primary" loading="save" class="w-full sm:w-auto">
                    <span class="text-lg font-bold">
                        {{ __('Update') }}
                    </span>
                </flux:button>
            </div>
        </div>
    </div>
</div>