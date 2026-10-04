<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

new class extends Component {
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Update the password for the currently authenticated user.
     */
    public function updatePassword(): void
    {
        $user = Auth::user();

        try {
            $validated = $this->validate([
                // Pas de mot de passe actuel à saisir pour un compte qui n'en a pas ou qui vient
                // de se connecter par code (mot de passe oublié) : le code a prouvé l'accès au mail.
                'current_password' => $user->doitConfirmerMotDePasse() ? ['required', 'string', 'current_password'] : ['nullable'],
                'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        // Nouveau mot de passe : les autres sessions et les cookies « se souvenir de moi » sont révoqués.
        $user->forceFill([
            'mot_de_passe' => $validated['password'],
            'remember_token' => Str::random(60),
        ])->save();

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->getAuthIdentifier())
                ->where('id', '!=', session()->getId())
                ->delete();
        }

        $this->reset('current_password', 'password', 'password_confirmation');

        $this->dispatch('password-updated');
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout heading="Update password" subheading="Ensure your account is using a long, random password to stay secure">
        <form wire:submit="updatePassword" class="mt-6 space-y-6">
            @if (auth()->user()->doitConfirmerMotDePasse())
                <flux:input
                    wire:model="current_password"
                    id="update_password_current_passwordpassword"
                    label="{{ __('Current password') }}"
                    type="password"
                    name="current_password"
                    required
                    autocomplete="current-password"
                />
            @endif
            <flux:input
                wire:model="password"
                id="update_password_password"
                label="{{ __('New password') }}"
                type="password"
                name="password"
                required
                autocomplete="new-password"
            />
            <flux:input
                wire:model="password_confirmation"
                id="update_password_password_confirmation"
                label="{{ __('Confirm Password') }}"
                type="password"
                name="password_confirmation"
                required
                autocomplete="new-password"
            />

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full">{{ __('Save') }}</flux:button>
                </div>

                <x-action-message class="me-3" on="password-updated">
                    {{ __('Saved.') }}
                </x-action-message>
            </div>
        </form>
    </x-settings.layout>
</section>
