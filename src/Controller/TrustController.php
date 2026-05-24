<?php
declare(strict_types=1);

namespace App\Controller;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Model\BlockRepository;
use App\Model\ReportRepository;
use App\Model\UserRepository;

/**
 * Trust & safety : signalement + blocage (bloquants MVP Tomas)
 * Porte de sortir/app/Http/Controllers/Api/TrustController.php
 */
class TrustController
{
    public function report(Request $request): void
    {
        $user = $request->user();
        $data = $request->body();
        $v = new Validator();
        [$ok, $errors, $clean] = $v->check($data, [
            'type' => 'required|in:user,event',
            'id' => 'required|integer',
            'reason' => 'required|string|max:120',
            'details' => 'nullable|string|max:2000',
        ]);
        if (!$ok) {
            Response::validationError($errors);
            return;
        }

        $reports = new ReportRepository();
        if (!$reports->targetExists($clean['type'], (int) $clean['id'])) {
            Response::error('Target not found', 404);
            return;
        }

        $id = $reports->create(
            (int) $user['id'],
            $clean['type'],
            (int) $clean['id'],
            $clean['reason'],
            $clean['details'] ?? null
        );

        Response::json([
            'message' => 'Signalement enregistre. Notre equipe de moderation va l\'examiner.',
            'report_id' => $id,
        ], 201);
    }

    public function block(Request $request): void
    {
        $user = $request->user();
        $data = $request->body();
        $v = new Validator();
        [$ok, $errors, $clean] = $v->check($data, [
            'user_id' => 'required|integer',
        ]);
        if (!$ok) {
            Response::validationError($errors);
            return;
        }

        if ((int) $clean['user_id'] === (int) $user['id']) {
            Response::json(['message' => 'Impossible de se bloquer soi-meme.'], 422);
            return;
        }

        $users = new UserRepository();
        if (!$users->find((int) $clean['user_id'])) {
            Response::error('User not found', 404);
            return;
        }

        $id = (new BlockRepository())->createOrFind((int) $user['id'], (int) $clean['user_id']);
        Response::json([
            'message' => 'Utilisateur bloque.',
            'block_id' => $id,
        ], 201);
    }

    public function unblock(Request $request): void
    {
        $user = $request->user();
        $data = $request->body();
        $v = new Validator();
        [$ok, $errors, $clean] = $v->check($data, [
            'user_id' => 'required|integer',
        ]);
        if (!$ok) {
            Response::validationError($errors);
            return;
        }
        (new BlockRepository())->delete((int) $user['id'], (int) $clean['user_id']);
        Response::json(['message' => 'Blocage leve.']);
    }
}
