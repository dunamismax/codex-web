<?php

namespace App\Livewire;

use Livewire\Component;

class CodexChat extends Component
{
    public string $streamUrl;

    public string $defaultCwd;

    public ?string $defaultModel;

    public bool $defaultFullAuto;

    public function mount(): void
    {
        $this->streamUrl = route('codex.stream');
        $this->defaultCwd = (string) config('codex.default_cwd', base_path());
        $this->defaultModel = config('codex.default_model');
        $this->defaultFullAuto = (bool) config('codex.default_full_auto', true);
    }

    public function render()
    {
        return view('livewire.codex-chat');
    }
}
