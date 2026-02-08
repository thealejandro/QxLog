<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;

use function Livewire\Volt\{state, computed, mount};

state([
    'q' => '',
]);

mount(function () {
    abort_unless(Auth::check(), 401);
    abort_unless((bool) Auth::user()->role !== 'admin', 403);
});

$instrumentists = computed(function () {
    return User::query()
        ->where('role', 'instrumentist')
        ->when($this->q, function ($q) {
            $term = trim($this->q);
            $q->where(function ($s) use ($term) {
                $s->where('name', 'like', "%{$term}%")
                    ->orWhere('username', 'like', "%{$term}%");
            });
        })
        ->orderBy('name')
        ->get(['id', 'name', 'username', 'use_pay_scheme']);
});

$toggle = function (int $id) {
    abort_unless((bool) Auth::user()->role !== 'admin', 403);

    $u = User::query()
        ->where('role', 'instrumentist')
        ->findOrFail($id);

    $u->use_pay_scheme = !(bool) $u->use_pay_scheme;
    $u->save();
};

?>

<div class="max-w-4xl mx-auto p-4 space-y-6">
    <div class="mb-4">
        <flux:heading size="xl">{{ __('Instrumentists') }}</flux:heading>
        <flux:subheading>{{ __('Mark who uses special payment scheme') }}</flux:subheading>
    </div>

    <div class="space-y-4">
        <flux:input icon="magnifying-glass" wire:model.live="q" placeholder="{{ __('Search name or username...') }}" />

        <!-- Mobile View (Cards) -->
        <div class="grid grid-cols-1 gap-4 sm:hidden">
            @forelse($this->instrumentists as $u)
                <div
                    class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 shadow-sm space-y-3">
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $u->name }}</div>
                            <div class="text-sm text-zinc-500">{{ $u->username }}</div>
                        </div>
                        <flux:badge size="sm" color="{{ $u->use_pay_scheme ? 'green' : 'zinc' }}">
                            {{ $u->use_pay_scheme ? __('Special') : __('Standard') }}
                        </flux:badge>
                    </div>
                    <div class="pt-2 border-t border-zinc-100 dark:border-zinc-800">
                        <flux:button size="sm"
                            class="w-full !bg-indigo-50 dark:!bg-indigo-900/30 !text-indigo-600 dark:!text-indigo-400 !border-indigo-100 dark:!border-indigo-800"
                            wire:click="toggle({{ $u->id }})">
                            {{ $u->use_pay_scheme ? __('Deactivate scheme') : __('Activate scheme') }}
                        </flux:button>
                    </div>
                </div>
            @empty
                <div class="p-4 text-center text-zinc-500 dark:text-zinc-400 italic">
                    {{ __('No instrumentists.') }}
                </div>
            @endforelse
        </div>

        <!-- Desktop View (Table) -->
        <div class="hidden sm:block overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-800/50">
                    <tr>
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-zinc-500 uppercase tracking-wider">
                            {{ __('Name') }}
                        </th>
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-zinc-500 uppercase tracking-wider">
                            {{ __('Username') }}
                        </th>
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-zinc-500 uppercase tracking-wider">
                            {{ __('Scheme') }}
                        </th>
                        <th scope="col"
                            class="px-6 py-3 text-right text-xs font-medium text-zinc-500 uppercase tracking-wider">
                            {{ __('Action') }}
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-zinc-900 divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($this->instrumentists as $u)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $u->name }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-zinc-500 dark:text-zinc-400">
                                {{ $u->username }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <flux:badge size="sm" color="{{ $u->use_pay_scheme ? 'green' : 'zinc' }}">
                                    {{ $u->use_pay_scheme ? __('Special') : __('Standard') }}
                                </flux:badge>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                <flux:button size="sm" variant="subtle" class="!text-indigo-600 dark:!text-indigo-400"
                                    wire:click="toggle({{ $u->id }})">
                                    {{ __('Change') }}
                                </flux:button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-8 text-center text-sm text-zinc-500 dark:text-zinc-400 italic">
                                {{ __('No instrumentists.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>