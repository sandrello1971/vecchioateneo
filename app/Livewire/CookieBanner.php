<?php

namespace App\Livewire;

use Livewire\Component;

class CookieBanner extends Component
{
    public bool $show = false;

    public function mount()
    {
        $this->show = !isset($_COOKIE['atheneum_cookie_consent']);
    }

    public function acceptAll()
    {
        setcookie('atheneum_cookie_consent', 'all', time() + (365 * 24 * 60 * 60), '/');
        $this->show = false;
    }

    public function acceptNecessary()
    {
        setcookie('atheneum_cookie_consent', 'necessary', time() + (365 * 24 * 60 * 60), '/');
        $this->show = false;
    }

    public function render()
    {
        return view('livewire.cookie-banner');
    }
}
