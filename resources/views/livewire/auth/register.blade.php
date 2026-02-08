<x-layouts.auth>
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Create Account')" :description="__('Enter your details to create your account')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-6">
            @csrf
            <!-- Name -->
            <flux:input name="name" :label="__('Name')" :value="old('name')" type="text" required autofocus
                autocomplete="name" :placeholder="__('Full Name')" />

            <!-- Username -->
            <flux:input name="username" :label="__('Username')" :value="old('username')" type="text" required
                autocomplete="username" :placeholder="__('Username')" />

            <!-- Role -->
            <div>
                <flux:label>{{ __('Role') }}</flux:label>
                <select name="role" required
                    class="w-full rounded-lg border-zinc-200 bg-white dark:bg-zinc-800 dark:border-zinc-700 text-sm p-2.5">
                    <option value="">{{ __('Select Role') }}</option>
                    <option value="doctor">{{ __('Doctor') }}</option>
                    <option value="instrumentist">{{ __('Instrumentist') }}</option>
                    <option value="circulating">{{ __('Circulating') }}</option>
                </select>
            </div>

            <!-- Phone -->
            <flux:input name="phone" :label="__('Phone')" :value="old('phone')" type="tel" :placeholder="__('Phone')" />

            <!-- Email Address -->
            <flux:input name="email" :label="__('Email')" :value="old('email')" type="email" required
                autocomplete="email" placeholder="email@example.com" />

            <!-- Password -->
            <flux:input name="password" :label="__('Password')" type="password" required autocomplete="new-password"
                :placeholder="__('Password')" viewable />

            <!-- Confirm Password -->
            <flux:input name="password_confirmation" :label="__('Confirm Password')" type="password" required
                autocomplete="new-password" :placeholder="__('Confirm Password')" viewable />

            <div class="flex items-center justify-end">
                <flux:button type="submit" variant="primary" class="w-full" data-test="register-user-button">
                    {{ __('Register') }}
                </flux:button>
            </div>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
            <span>{{ __('Already have an account?') }}</span>
            <flux:link :href="route('login')" wire:navigate>{{ __('Log in') }}</flux:link>
        </div>
    </div>
</x-layouts.auth>