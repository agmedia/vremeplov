<?php

namespace App\Services;

use App\Mail\ContractTerminationConfirmation;
use App\Mail\ContractTerminationMessage;
use App\Models\ContractTermination;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ContractTerminationNotificationService
{
    public function send(ContractTermination $termination): void
    {
        $errors = [];
        $data = $termination->mailData();

        try {
            Mail::to($termination->email)->send(new ContractTerminationConfirmation($data));
            $termination->forceFill(['consumer_notified_at' => now()])->save();
        } catch (\Throwable $exception) {
            $errors[] = 'Potvrda korisniku: ' . $exception->getMessage();
            Log::warning('Contract termination consumer notification failed.', [
                'termination_id' => $termination->id,
                'error' => $exception->getMessage(),
            ]);
        }

        try {
            Mail::to(config('mail.admin'))->send(new ContractTerminationMessage($data));
            $termination->forceFill(['admin_notified_at' => now()])->save();
        } catch (\Throwable $exception) {
            $errors[] = 'Obavijest administratoru: ' . $exception->getMessage();
            Log::warning('Contract termination admin notification failed.', [
                'termination_id' => $termination->id,
                'error' => $exception->getMessage(),
            ]);
        }

        $termination->forceFill([
            'notification_error' => $errors ? implode("\n", $errors) : null,
        ])->save();
    }
}
