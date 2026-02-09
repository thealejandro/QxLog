<x-layouts.app :title="__('Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">

        @if(auth()->user()->role === 'admin')
            <div class="grid auto-rows-min gap-4 md:grid-cols-2 lg:grid-cols-3">
                <!-- Admin Shortcuts -->
                <a href="{{ route('procedures.index') }}" wire:navigate
                    class="group relative flex flex-col justify-between overflow-hidden rounded-xl border border-zinc-200 p-6 hover:border-zinc-300 dark:border-zinc-700 dark:hover:border-zinc-600 bg-white dark:bg-zinc-900 transition-colors">
                    <div>
                        <div
                            class="mb-4 inline-flex items-center justify-center rounded-lg bg-indigo-100 p-3 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400">
                            <flux:icon.layout-grid class="size-6" />
                        </div>
                        <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Procedimientos') }}</h3>
                        <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                            {{ __('Ver todos los procedimientos registrados.') }}</p>
                    </div>
                </a>

                <a href="{{ route('payouts.index') }}" wire:navigate
                    class="group relative flex flex-col justify-between overflow-hidden rounded-xl border border-zinc-200 p-6 hover:border-zinc-300 dark:border-zinc-700 dark:hover:border-zinc-600 bg-white dark:bg-zinc-900 transition-colors">
                    <div>
                        <div
                            class="mb-4 inline-flex items-center justify-center rounded-lg bg-emulator-100 p-3 text-emulator-600 dark:bg-emulator-900/30 dark:text-emulator-400">
                            <flux:icon.banknotes class="size-6" />
                        </div>
                        <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Pagos') }}</h3>
                        <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                            {{ __('Gestionar y ver historial de pagos.') }}</p>
                    </div>
                </a>

                <a href="{{ route('pricing.settings') }}" wire:navigate
                    class="group relative flex flex-col justify-between overflow-hidden rounded-xl border border-zinc-200 p-6 hover:border-zinc-300 dark:border-zinc-700 dark:hover:border-zinc-600 bg-white dark:bg-zinc-900 transition-colors">
                    <div>
                        <div
                            class="mb-4 inline-flex items-center justify-center rounded-lg bg-zinc-100 p-3 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400">
                            <flux:icon.wrench class="size-6" />
                        </div>
                        <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Configuración') }}</h3>
                        <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                            {{ __('Ajustar precios y configuraciones.') }}</p>
                    </div>
                </a>
            </div>
        @elseif(auth()->user()->role === 'instrumentist')
            <div class="grid auto-rows-min gap-4 md:grid-cols-2 lg:grid-cols-3">
                <!-- Instrumentist Shortcuts -->
                <a href="{{ route('instrumentist.payouts') }}" wire:navigate
                    class="group relative flex flex-col justify-between overflow-hidden rounded-xl border border-zinc-200 p-6 hover:border-zinc-300 dark:border-zinc-700 dark:hover:border-zinc-600 bg-white dark:bg-zinc-900 transition-colors">
                    <div>
                        <div
                            class="mb-4 inline-flex items-center justify-center rounded-lg bg-emulator-100 p-3 text-emulator-600 dark:bg-emulator-900/30 dark:text-emulator-400">
                            <flux:icon.banknotes class="size-6" />
                        </div>
                        <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Mis Pagos') }}</h3>
                        <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                            {{ __('Ver mi historial de pagos recibidos.') }}</p>
                    </div>
                </a>

                <a href="{{ route('profile.edit') }}" wire:navigate
                    class="group relative flex flex-col justify-between overflow-hidden rounded-xl border border-zinc-200 p-6 hover:border-zinc-300 dark:border-zinc-700 dark:hover:border-zinc-600 bg-white dark:bg-zinc-900 transition-colors">
                    <div>
                        <div
                            class="mb-4 inline-flex items-center justify-center rounded-lg bg-zinc-100 p-3 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400">
                            <flux:icon.cog class="size-6" />
                        </div>
                        <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Mi Perfil') }}</h3>
                        <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                            {{ __('Actualizar mis datos y contraseña.') }}</p>
                    </div>
                </a>
            </div>
        @else
            <!-- Default / Other roles -->
            <div class="grid auto-rows-min gap-4 md:grid-cols-2 lg:grid-cols-3">
                <a href="{{ route('profile.edit') }}" wire:navigate
                    class="group relative flex flex-col justify-between overflow-hidden rounded-xl border border-zinc-200 p-6 hover:border-zinc-300 dark:border-zinc-700 dark:hover:border-zinc-600 bg-white dark:bg-zinc-900 transition-colors">
                    <div>
                        <div
                            class="mb-4 inline-flex items-center justify-center rounded-lg bg-zinc-100 p-3 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400">
                            <flux:icon.cog class="size-6" />
                        </div>
                        <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Mi Perfil') }}</h3>
                        <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                            {{ __('Actualizar mis datos y contraseña.') }}</p>
                    </div>
                </a>
            </div>
        @endif

    </div>
</x-layouts.app>