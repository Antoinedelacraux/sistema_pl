<?php

namespace App\Jobs;

use App\Mail\IngresoRegistradoMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EnviarNotificacionIngresoJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public array $payload
    ) {}

    public function handle(): void
    {
        $destinatarios = config('registro.ingreso.notify_to', []);
        if ($destinatarios === []) {
            return;
        }

        try {
            Mail::to($destinatarios)->send(new IngresoRegistradoMail($this->payload));
        } catch (\Throwable $exception) {
            Log::warning('No se pudo enviar correo de ingreso registrado.', [
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
