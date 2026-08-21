{{-- resources/views/livewire/shared/session-warning.blade.php — Content_guidelines.md §5.17.
     Mobile: full-width bottom sheet. Desktop: centred dialog. Countdown
     announced at 60s and 30s only — see App\Livewire\Shared\SessionWarning's
     class doc for what's genuinely specified here vs. this component's own
     reasonable defaults absent the missing App Flow §3.6. --}}
<div>
    @if($show)
        <div
            x-data="{
                secondsRemaining: 120,
                countdownText: '',
                tick: null,
                init() {
                    this.tick = setInterval(() => {
                        this.secondsRemaining--;
                        if (this.secondsRemaining === 60 || this.secondsRemaining === 30) {
                            this.countdownText = this.secondsRemaining + ' {{ __('seconds remaining before you are signed out.') }}';
                        }
                        if (this.secondsRemaining <= 0) clearInterval(this.tick);
                    }, 1000);
                },
            }"
            x-on:session-extended.window="secondsRemaining = 120; countdownText = ''"
            x-trap.inert.noscroll="true"
            role="dialog"
            aria-modal="true"
            aria-labelledby="session-warning-title"
            class="session-warning fixed inset-x-0 bottom-0 z-50 bg-surface-raised border-t border-border shadow-modal p-6
                   md:inset-0 md:m-auto md:h-fit md:max-w-modal-md md:rounded-lg md:border md:border-b"
        >
            <h2
                id="session-warning-title"
                tabindex="-1"
                x-init="$nextTick(() => $el.focus())"
                class="text-xl font-semibold text-ink mb-2"
            >{{ __('You will be signed out soon') }}</h2>

            <p class="sr-only" aria-live="polite" x-text="countdownText"></p>

            @if($autosaveFailed)
                <p class="text-base text-ink mb-4">
                    {{ __('We could not save :section. Your session is about to end.', ['section' => $failedSection]) }}
                </p>
            @elseif($savedAt)
                <p class="text-base text-ink mb-4">
                    {{ __('Your answers were saved at :time', ['time' => $savedAt]) }}
                </p>
            @endif

            <div class="flex flex-col md:flex-row gap-3">
                <x-button wire:click="stayeSignedIn" variant="primary">{{ __('Stay signed in') }}</x-button>

                @if($autosaveFailed)
                    <x-button wire:click="retryAutosave" variant="secondary">{{ __('Try again') }}</x-button>
                    <x-button x-on:click="$dispatch('copy-answers')" variant="ghost">{{ __('Copy my answers') }}</x-button>
                @endif

                <form method="POST" action="{{ url('/logout') }}">
                    @csrf
                    <x-button type="submit" variant="ghost">{{ __('Sign out now') }}</x-button>
                </form>
            </div>
        </div>
    @endif
</div>
