<?php
declare(strict_types=1);

namespace App\Service\Sms;

use App\Core\Bootstrap;
use App\Core\Logger;

/**
 * Driver SMS MOCK (gratuit, defaut).
 * Aucun cout, aucun service externe. Ecrit dans logs/sms.log.
 * En dev, peut exposer le code OTP en clair (pour test E2E).
 *
 * Regle DIG : aucun service payant sans GO Stephane explicite.
 */
class LogSmsSender implements SmsSender
{
    public function send(string $to, string $message): ?string
    {
        Logger::info('sms', '[SMS MOCK] envoi simule (aucun SMS reel)', [
            'to' => $to,
            'message' => $message,
            'driver' => $this->driverName(),
        ]);

        // Si config autorise expose_code_in_dev : on extrait le code et le retourne
        if (Bootstrap::config('sms.drivers.log.expose_code_in_dev', false)) {
            if (preg_match('/(\d{4,8})/', $message, $m)) {
                return $m[1];
            }
        }
        return null;
    }

    public function driverName(): string
    {
        return 'log';
    }
}
