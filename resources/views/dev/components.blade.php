<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Component library preview — local only</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="p-6 md:p-10 flex flex-col gap-12 max-w-panel mx-auto">

    <a href="#main-content" class="skip-link">Skip to main content</a>

    <main id="main-content" class="flex flex-col gap-12">

    <section>
        <h1 class="text-3xl mb-6">Design system component preview</h1>
        <p class="text-base text-ink-muted">Local-only route (S2.10). Not linked from any real page.</p>
    </section>

    <section>
        <h2 class="text-2xl mb-4">Button</h2>
        <div class="flex flex-wrap gap-3 items-center mb-4">
            <x-button variant="primary">Save and continue</x-button>
            <x-button variant="secondary">Continue</x-button>
            <x-button variant="ghost">Cancel</x-button>
            <x-button variant="danger">Delete draft</x-button>
        </div>
        <div class="flex flex-wrap gap-3 items-center mb-4">
            <x-button size="sm">Small</x-button>
            <x-button size="md">Medium</x-button>
            <x-button size="lg">Large</x-button>
        </div>
        <div class="flex flex-wrap gap-3 items-center mb-4">
            <x-button :loading="true">Submit application</x-button>
            <x-button :disabled="true" disabled-reason="Complete all sections first">Submit application</x-button>
            <x-button icon-start="arrow-up-tray">Upload</x-button>
        </div>
    </section>

    <section>
        <h2 class="text-2xl mb-4">Badge</h2>
        <div class="flex flex-wrap gap-3">
            <x-badge status="complete" />
            <x-badge status="in_progress" />
            <x-badge status="not_started" />
            <x-badge status="needs_attention" />
            <x-badge status="rejected" />
            <x-badge status="draft" />
            <x-badge status="submitted" />
            <x-badge status="action_required" />
        </div>
    </section>

    <section>
        <h2 class="text-2xl mb-4">Alert</h2>
        <div class="flex flex-col gap-4">
            <x-alert tone="info" title="Payment safe">We're waiting for confirmation from our payment provider.</x-alert>
            <x-alert tone="success" title="Draft saved">Your answers were saved.</x-alert>
            <x-alert tone="warning" title="Action required">Upload a replacement document.</x-alert>
            <x-alert tone="danger" title="Payment declined" :dismissible="true">Your card was declined. Your application is safe and unchanged.</x-alert>
        </div>
    </section>

    <section>
        <h2 class="text-2xl mb-4">Card</h2>
        <div class="grid md:grid-cols-2 gap-4">
            <x-card>Static card content.</x-card>
            <x-card :interactive="true" href="#main-content">Interactive card (whole card is a link).</x-card>
        </div>
    </section>

    <section class="max-w-content">
        <h2 class="text-2xl mb-4">FieldGroup and inputs</h2>
        <x-field-group label="Email address" name="preview_email" required hint="We will never share this.">
            <x-input name="preview_email" type="email" />
        </x-field-group>

        <x-field-group label="Email address" name="preview_email_error" required error="Enter a valid email address">
            <x-input name="preview_email_error" type="email" error="Enter a valid email address" />
        </x-field-group>

        <x-field-group label="Notes" name="preview_notes">
            <x-textarea name="preview_notes" />
        </x-field-group>

        <x-field-group label="Nationality" name="preview_country">
            <x-select name="preview_country" placeholder="Choose a country" :options="['IN' => 'India', 'US' => 'United States']" />
        </x-field-group>

        <x-checkbox name="preview_terms" label="I agree to the terms" />

        <div class="mt-5">
            <x-radio-group name="preview_sex" legend="Sex" :options="['male' => 'Male', 'female' => 'Female']" />
        </div>

        <div class="mt-5">
            <x-date-input name="preview_dob" label="Date of birth" />
        </div>

        <div class="mt-5">
            <x-error-summary :errors="$errors" />
        </div>
    </section>

    <section>
        <h2 class="text-2xl mb-4">EmptyState</h2>
        <x-card>
            <x-empty-state icon="document-duplicate" headline="You haven't started any applications" body="When you start one, it'll appear here.">
                <x-slot:action>
                    <x-button>Start an application</x-button>
                </x-slot:action>
            </x-empty-state>
        </x-card>
    </section>

    <section class="max-w-content">
        <h2 class="text-2xl mb-4">ProgressBar</h2>
        <x-progress-bar :value="4" :max="7" label="sections complete" />
    </section>

    <section class="max-w-content">
        <h2 class="text-2xl mb-4">Skeleton</h2>
        <div class="flex flex-col gap-2">
            <x-skeleton class="w-full h-6" />
            <x-skeleton class="w-full h-6" />
            <x-skeleton class="w-32 h-4" />
        </div>
    </section>

    <section>
        <h2 class="text-2xl mb-4">Modal</h2>
        <x-button x-on:click="$dispatch('open-modal', 'preview-modal')">Open modal</x-button>

        <x-modal name="preview-modal" title="Delete draft VISA-IND-2026-8K3F2Q?">
            <p class="text-base text-ink">This permanently deletes your answers and uploaded documents. You can't undo this.</p>
            <x-slot:footer>
                <x-button variant="secondary" x-on:click="$dispatch('close-modal')">Cancel</x-button>
                <x-button variant="danger" x-on:click="$dispatch('close-modal')">Delete draft</x-button>
            </x-slot:footer>
        </x-modal>
    </section>

    <section>
        <h2 class="text-2xl mb-4">Toast (Livewire)</h2>
        <x-button x-on:click="$dispatch('toast', { message: 'Draft saved.' })">Fire a toast</x-button>
        @livewire('shared.toast')
    </section>

    <section>
        <h2 class="text-2xl mb-4">SessionWarning (Livewire)</h2>
        <x-button x-on:click="$dispatch('session-expiring')">Trigger session warning</x-button>
        @livewire('shared.session-warning')
    </section>

    </main>

    @livewireScripts
</body>
</html>
