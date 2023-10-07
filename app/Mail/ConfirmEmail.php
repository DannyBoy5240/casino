<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ConfirmEmail extends Mailable {

    use Queueable, SerializesModels;

    private string $token;

    public function __construct(string $token) {
        $this->token = $token;
    }

    public function build() {
        return $this->view('mail.confirmEmail')->with(['token' => $this->token]);
    }

}
