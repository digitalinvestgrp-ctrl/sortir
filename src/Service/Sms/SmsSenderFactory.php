<?php
declare(strict_types=1);

namespace App\Service\Sms;

use App\Core\Bootstrap;

class SmsSenderFactory
{
    public static function make(): SmsSender
    {
        $driver = Bootstrap::config('sms.driver', 'log');
        // V1 : seul le mock est cable. Twilio = a brancher avec GO Stephane explicite.
        if ($driver === 'log') {
            return new LogSmsSender();
        }
        // Fallback safe : mock
        return new LogSmsSender();
    }
}
