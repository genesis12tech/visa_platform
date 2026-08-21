<?php

namespace App\Livewire\Shared;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Content_guidelines.md §5.17. The full trigger-timing spec lives in App Flow
 * §3.6, which does not exist in this repository (CLAUDE.md's documented
 * gap) — this component is built strictly from §5.17's own bullet list, plus
 * the 30-minute session timeout implied by §7.6's "Session expired" copy.
 * The page-specific autosave mechanism doesn't exist yet either (Stage 3+);
 * this component is a self-contained shell that any future autosave
 * integration wires up via the session-expiring / autosave-completed /
 * autosave-failed events, not a finished end-to-end feature.
 */
class SessionWarning extends Component
{
    public bool $show = false;

    public ?string $savedAt = null;

    public bool $autosaveFailed = false;

    public ?string $failedSection = null;

    #[On('session-expiring')]
    public function warn(): void
    {
        $this->show = true;
    }

    #[On('autosave-completed')]
    public function autosaveCompleted(string $savedAt): void
    {
        $this->savedAt = $savedAt;
        $this->autosaveFailed = false;
    }

    #[On('autosave-failed')]
    public function autosaveFailedEvent(string $section): void
    {
        $this->autosaveFailed = true;
        $this->failedSection = $section;
    }

    /**
     * Any Livewire round-trip already resets Laravel's own session
     * inactivity clock via the StartSession middleware — this method's real
     * job is closing the modal and telling the client-side countdown to
     * restart, not extending the session itself.
     */
    public function stayeSignedIn(): void
    {
        $this->show = false;
        $this->dispatch('session-extended');
    }

    public function retryAutosave(): void
    {
        $this->dispatch('retry-autosave');
    }

    public function render(): View
    {
        return view('livewire.shared.session-warning');
    }
}
