<?php
declare(strict_types=1);

namespace App\Service;

use App\Core\Bootstrap;
use App\Model\PhoneVerificationRepository;
use App\Model\UserRepository;
use App\Service\Sms\SmsSender;
use App\Service\Sms\SmsSenderFactory;

/**
 * Flux verification telephone (exigence trust bloquante Tomas, portee de sortir)
 *
 * - genere un code OTP, le HASHE en BDD, l'envoie via SmsSender (mock log par defaut)
 * - verifie le code : expiration + plafond de tentatives
 */
class PhoneVerificationService
{
    private SmsSender $sms;
    private PhoneVerificationRepository $repo;
    private UserRepository $users;

    public function __construct(
        ?SmsSender $sms = null,
        ?PhoneVerificationRepository $repo = null,
        ?UserRepository $users = null
    ) {
        $this->sms = $sms ?? SmsSenderFactory::make();
        $this->repo = $repo ?? new PhoneVerificationRepository();
        $this->users = $users ?? new UserRepository();
    }

    /**
     * Genere et envoie un code. Retourne le code en clair UNIQUEMENT si driver mock l'expose.
     */
    public function sendCode(int $userId, string $phone): ?string
    {
        $length = (int) Bootstrap::config('sms.otp.length', 6);
        $max = (int) (10 ** $length) - 1;
        $code = str_pad((string) random_int(0, $max), $length, '0', STR_PAD_LEFT);

        $hash = password_hash($code, PASSWORD_BCRYPT, ['cost' => 10]);
        $ttl = (int) Bootstrap::config('sms.otp.ttl_minutes', 10);
        $this->repo->create($userId, $phone, $hash, $ttl);

        $message = "trouvetateam : votre code de verification est {$code}. Valable {$ttl} minutes.";
        return $this->sms->send($phone, $message);
    }

    /**
     * @return array{ok: bool, reason?: string}
     */
    public function verifyCode(int $userId, string $phone, string $code): array
    {
        $record = $this->repo->findLatestPending($userId, $phone);
        if (!$record) {
            return ['ok' => false, 'reason' => 'no_pending_code'];
        }
        if ($this->repo->isExpired($record)) {
            return ['ok' => false, 'reason' => 'expired'];
        }
        $maxAttempts = (int) Bootstrap::config('sms.otp.max_attempts', 5);
        if ((int) $record['attempts'] >= $maxAttempts) {
            return ['ok' => false, 'reason' => 'too_many_attempts'];
        }
        $this->repo->incrementAttempts((int) $record['id']);

        if (!password_verify($code, $record['code_hash'])) {
            return ['ok' => false, 'reason' => 'invalid_code'];
        }

        $this->repo->markConsumed((int) $record['id']);
        $this->users->markPhoneVerified($userId, $phone);

        return ['ok' => true];
    }
}
