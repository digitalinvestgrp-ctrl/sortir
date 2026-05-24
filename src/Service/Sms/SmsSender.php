<?php
declare(strict_types=1);

namespace App\Service\Sms;

/**
 * Contract SMS sender. Implementations : LogSmsSender (mock gratuit), TwilioSmsSender (payant, OFF par defaut)
 */
interface SmsSender
{
    /**
     * Envoie un SMS. Retourne le code OTP en clair UNIQUEMENT pour driver mock+dev (sinon null).
     */
    public function send(string $to, string $message): ?string;

    public function driverName(): string;
}
