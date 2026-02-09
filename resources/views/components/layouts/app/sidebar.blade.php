<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('partials.head')
</head>

<body class="min-h-screen bg-white dark:bg-zinc-950">
    <flux:sidebar sticky stashable class="border-e border-zinc-200 bg-indigo-50 dark:border-zinc-800 dark:bg-zinc-700">
        <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />


        <flux:brand href="{{ route('dashboard') }}" name="QxLog" class="me-5 flex items-center rtl:space-x-reverse"
            wire:navigate>
            <x-slot name="logo" class="bg-accent text-accent-foreground">
                <i class="font-serif font-bold">A</i>
            </x-slot>
        </flux:brand>

        <flux:navlist variant="outline">
            @if(auth()->user()->role !== "admin" && auth()->check())
                <flux:navlist.group :heading="__('Procedures')" class="grid">
                    @if(!auth()->check())
                        <flux:navlist.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')"
                            wire:navigate>
                            {{ __('Dashboard') }}
                        </flux:navlist.item>
                    @endif

                    <flux:navlist.item icon="home" :href="route('procedures.create')"
                        :current="request()->routeIs('procedures.create')" wire:navigate>
                        {{ __('Register Procedure') }}
                    </flux:navlist.item>
                </flux:navlist.group>
            @endif

            @if(auth()->user()->role === 'admin')
                <flux:navlist.group :heading="__('Payouts')" class="grid">
                    <flux:navlist.item icon="home" :href="route('payouts.create')"
                        :current="request()->routeIs('payouts.create')" wire:navigate>
                        {{ __('Make Payment') }}
                    </flux:navlist.item>
                </flux:navlist.group>
                <flux:navlist.group :heading="__('History')" class="grid">
                    <flux:navlist.item icon="layout-grid" :href="route('payouts.index')"
                        :current="request()->routeIs('payouts.index')" wire:navigate>
                        {{ __('Payment History') }}
                    </flux:navlist.item>
                    <flux:navlist.item icon="layout-grid" :href="route('procedures.index')"
                        :current="request()->routeIs('procedures.index')" wire:navigate>
                        {{ __('Procedure History') }}
                    </flux:navlist.item>
                </flux:navlist.group>
                <flux:navlist.group :heading="__('Configurations')" class="grid">
                    <flux:navlist.item icon="users" :href="route('pricing.instrumentists')"
                        :current="request()->routeIs('pricing.instrumentists')" wire:navigate>
                        {{ __('Configure Instrumentists') }}
                    </flux:navlist.item>
                    <flux:navlist.item icon="wrench" :href="route('pricing.settings')"
                        :current="request()->routeIs('pricing.settings')" wire:navigate>
                        {{ __('Instrumentist Pricing') }}
                    </flux:navlist.item>
                </flux:navlist.group>
            @endif
        </flux:navlist>

        <flux:spacer />

        <flux:navlist variant="outline">
            @if(auth()->user()->is_super_admin)
                <flux:navlist.item icon="user" :href="route('users.index')" :current="request()->routeIs('users.index')"
                    wire:navigate>
                    {{ __('Users') }}
                </flux:navlist.item>
            @endif
        </flux:navlist>

        <flux:sidebar.nav>
            <flux:sidebar.item icon="cog" :href="route('profile.edit')" :current="request()->routeIs('profile.edit')"
                wire:navigate>
                {{ __('Settings') }}
            </flux:sidebar.item>

            <form method="POST" action="{{ route('logout') }}" class="w-full">
                @csrf
                <flux:sidebar.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full"
                    data-test="logout-button">
                    {{ __('Log Out') }}
                </flux:sidebar.item>
            </form>
        </flux:sidebar.nav>

        <!-- Desktop User Menu -->
        <flux:dropdown class="hidden lg:block" position="bottom" align="start">
            <flux:profile :name="auth()->user()->name" :initials="auth()->user()->initials()"
                icon:trailing="chevrons-up-down" data-test="sidebar-menu-button" circle color="auto" />

            <flux:menu class="w-[220px]">
                <flux:menu.radio.group>
                    <div class="p-0 text-sm font-normal">
                        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                            <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                <span
                                    class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">
                                    {{ auth()->user()->initials() }}
                                </span>
                            </span>

                            <div class="grid flex-1 text-start text-sm leading-tight">
                                <span class="truncate font-semibold">{{ auth()->user()->name }}</span>
                                <span class="truncate text-xs">{{ auth()->user()->email }}</span>
                            </div>
                        </div>
                    </div>
                </flux:menu.radio.group>

                <flux:menu.separator />

                <flux:menu.radio.group>
                    <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>{{ __('Settings') }}
                    </flux:menu.item>
                </flux:menu.radio.group>

                <flux:menu.separator />

                <form method="POST" action="{{ route('logout') }}" class="w-full">
                    @csrf
                    <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full"
                        data-test="logout-button">
                        {{ __('Log Out') }}
                    </flux:menu.item>
                </form>
            </flux:menu>
        </flux:dropdown>
    </flux:sidebar>

    <!-- Mobile User Menu -->
    <flux:header class="lg:hidden">
        <flux:sidebar.toggle
            class="lg:hidden ml-2 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg"
            icon="bars-3" inset="left" />

        <flux:spacer />

        <flux:dropdown position="top" align="end">
            <flux:profile :initials="auth()->user()->initials()" icon-trailing="chevron-down" />

            <flux:menu>
                <flux:menu.radio.group>
                    <div class="p-0 text-sm font-normal">
                        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                            <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                <span
                                    class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">
                                    {{ auth()->user()->initials() }}
                                </span>
                            </span>

                            <div class="grid flex-1 text-start text-sm leading-tight">
                                <span class="truncate font-semibold">{{ auth()->user()->name }}</span>
                                <span class="truncate text-xs">{{ auth()->user()->email }}</span>
                            </div>
                        </div>
                    </div>
                </flux:menu.radio.group>

                <flux:menu.separator />

                <flux:menu.radio.group>
                    <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>{{ __('Settings') }}
                    </flux:menu.item>
                </flux:menu.radio.group>

                <flux:menu.separator />

                <form method="POST" action="{{ route('logout') }}" class="w-full">
                    @csrf
                    <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full"
                        data-test="logout-button">
                        {{ __('Log Out') }}
                    </flux:menu.item>
                </form>
            </flux:menu>
        </flux:dropdown>
    </flux:header>

    {{ $slot }}

    @fluxScripts
</body>

</html>