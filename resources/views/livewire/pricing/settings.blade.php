<?php

use App\Models\PricingSetting;
use Illuminate\Support\Facades\Auth;

use function Livewire\Volt\{state, mount, rules};

state([
    'default_rate' => 200,
    'video_rate' => 300,
    'night_rate' => 350,
    'long_case_rate' => 350,
    'long_case_threshold_minutes' => 120,
    'night_start' => '22:00',
    'night_end' => '06:00',
    'success' => null,
]);

mount(function () {
    abort_unless(Auth::check(), 401);
    abort_unless((bool) Auth::user()->is_super_admin, 403);

    $s = PricingSetting::firstOrCreate(['id' => 1]);

    $this->default_rate = (float) $s->default_rate;
    $this->video_rate = (float) $s->video_rate;
    $this->night_rate = (float) $s->night_rate;
    $this->long_case_rate = (float) $s->long_case_rate;
    $this->long_case_threshold_minutes = (int) $s->long_case_threshold_minutes;
    $this->night_start = (string) $s->night_start;
    $this->night_end = (string) $s->night_end;
});

rules([
    'default_rate' => ['required', 'numeric', 'min:0'],
    'video_rate' => ['required', 'numeric', 'min:0'],
    'night_rate' => ['required', 'numeric', 'min:0'],
    'long_case_rate' => ['required', 'numeric', 'min:0'],
    'long_case_threshold_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
    'night_start' => ['required', 'date_format:H:i'],
    'night_end' => ['required', 'date_format:H:i'],
]);

$save = function () {
    abort_unless((bool) Auth::user()->is_super_admin, 403);

    $data = $this->validate();

    PricingSetting::updateOrCreate(['id' => 1], $data);

    $this->success = 'Precios guardados.';
};

?>

<div class="max-w-3xl mx-auto p-4">
    <div class="mb-4">
        <h1 class="text-xl font-semibold">Precios globales</h1>
        <p class="text-sm text-gray-600">Aplican al instrumentista con esquema especial</p>
    </div>

    @if($success)
        <div class="mb-4 rounded border border-green-200 bg-green-50 px-3 py-2 text-green-800">
            {{ $success }}
        </div>
    @endif

    <div class="rounded-lg border bg-white p-4 space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
                <label class="block text-sm font-medium">
                    Tarifa base (Q)
                </label>
                <input type="number" step="0.01" class="mt-1 w-full rounded border px-3 py-2"
                    wire:model.live="default_rate">
                @error('default_rate')
                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium">
                    Video cirugía (Q)
                </label>
                <input type="number" step="0.01" class="mt-1 w-full rounded border px-3 py-2"
                    wire:model.live="video_rate">
                @error('video_rate')
                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium">
                    Madrugada (Q)
                </label>
                <input type="number" step="0.01" class="mt-1 w-full rounded border px-3 py-2"
                    wire:model.live="night_rate">
                @error('night_rate')
                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium">
                    Procedimiento Largo (Q)
                </label>
                <input type="number" step="0.01" class="mt-1 w-full rounded border px-3 py-2"
                    wire:model.live="long_case_rate">
                @error('long_case_rate')
                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium">
                    Umbral "Procedimiento Largo" (min)
                </label>
                <input type="number" class="mt-1 w-full rounded border px-3 py-2"
                    wire:model.live="long_case_threshold_minutes">
                @error('long_case_threshold_minutes')
                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium">
                        Inicio Horario Inhabil
                    </label>
                    <input type="time" class="mt-1 w-full rounded border px-3 py-2" wire:model.live="night_start">
                    @error('night_start')
                        <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium">
                        Fin Horario Inhabil
                    </label>
                    <input type="time" class="mt-1 w-full rounded border px-3 py-2" wire:model.live="night_end">
                    @error('night_end')
                        <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        <div class="pt-2">
            <button class="rounded bg-black px-4 py-2 text-white" wire:click="save">
                Guardar
            </button>
        </div>
    </div>
</div>