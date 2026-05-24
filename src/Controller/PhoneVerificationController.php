<?php
declare(strict_types=1);

namespace App\Controller;

use App\Core\Bootstrap;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Service\PhoneVerificationService;

/**
 * Verification telephone OBLIGATOIRE (exigence trust bloquante Tomas, portee de sortir)
 * Provider SMS mocke par defaut (driver "log") : aucun cout, aucun service externe
 */
class PhoneVerificationController
{
    public function request(Request $request): void
    {
        $user = $request->user();
        $data = $request->body();
        $v = new Validator();
        [$ok, $errors, $clean] = $v->check($data, [
            'phone' => 'required|regex:/^\+[1-9]\d{7,14}$/',
        ]);
        if (!$ok) {
            Response::validationError($errors);
            return;
        }

        $service = new PhoneVerificationService();
        $exposedCode = $service->sendCode((int) $user['id'], $clean['phone']);

        $payload = [
            'message' => 'Code envoye (driver SMS : ' . Bootstrap::config('sms.driver') . ').',
            'sms_driver' => Bootstrap::config('sms.driver'),
        ];
        if ($exposedCode !== null) {
            $payload['debug_code'] = $exposedCode;
            $payload['debug_note'] = 'Code expose car driver SMS mocke en dev. Brancher un vrai provider sur GO Stephane.';
        }
        Response::json($payload);
    }

    public function confirm(Request $request): void
    {
        $user = $request->user();
        $data = $request->body();
        $v = new Validator();
        [$ok, $errors, $clean] = $v->check($data, [
            'phone' => 'required|regex:/^\+[1-9]\d{7,14}$/',
            'code' => 'required|string',
        ]);
        if (!$ok) {
            Response::validationError($errors);
            return;
        }

        $service = new PhoneVerificationService();
        $result = $service->verifyCode((int) $user['id'], $clean['phone'], $clean['code']);
        if (!$result['ok']) {
            Response::json([
                'message' => 'Verification echouee.',
                'reason' => $result['reason'],
            ], 422);
            return;
        }
        Response::json([
            'message' => 'Telephone verifie.',
            'phone_verified' => true,
        ]);
    }
}
