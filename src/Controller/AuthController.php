<?php
declare(strict_types=1);

namespace App\Controller;

use App\Core\Bootstrap;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Model\ProfileRepository;
use App\Model\UserRepository;
use App\Service\TokenService;

/**
 * Auth controller — register / login / me / logout
 * Porte de sortir/app/Http/Controllers/Api/AuthController.php
 *
 * Regle metier critique : GATE 18+ a l'inscription (veto Tomas — aucun compte mineur cree)
 */
class AuthController
{
    public function register(Request $request): void
    {
        $minAge = (int) Bootstrap::config('min_age', 18);
        $data = $request->body();

        $v = new Validator();
        [$ok, $errors, $clean] = $v->check($data, [
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:190|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'birthdate' => 'required|date|before_today',
        ]);
        if (!$ok) {
            Response::validationError($errors);
            return;
        }
        // Sanity password : au moins 1 lettre + 1 chiffre
        if (!preg_match('/[A-Za-z]/', $clean['password']) || !preg_match('/\d/', $clean['password'])) {
            Response::validationError(['password' => ['Le mot de passe doit contenir lettres et chiffres.']]);
            return;
        }

        // Gate 18+
        $age = (new \DateTimeImmutable($clean['birthdate']))->diff(new \DateTimeImmutable('now'))->y;
        if ($age < $minAge) {
            Response::validationError([
                'birthdate' => ["L'inscription est reservee aux personnes de {$minAge} ans et plus."]
            ]);
            return;
        }

        $users = new UserRepository();
        $userId = $users->create([
            'name' => $clean['name'],
            'email' => $clean['email'],
            'password' => password_hash($clean['password'], PASSWORD_BCRYPT, ['cost' => 12]),
            'birthdate' => $clean['birthdate'],
        ]);

        (new ProfileRepository())->create([
            'user_id' => $userId,
            'display_name' => $clean['name'],
        ]);

        $token = (new TokenService())->createForUser($userId, 'api');

        $user = $users->find($userId);
        Response::json([
            'message' => 'Compte cree. Verification du telephone requise avant de participer a une sortie.',
            'user' => $users->payload($user, $minAge),
            'token' => $token,
            'phone_verified' => false,
        ], 201);
    }

    public function login(Request $request): void
    {
        $data = $request->body();
        $v = new Validator();
        [$ok, $errors, $clean] = $v->check($data, [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);
        if (!$ok) {
            Response::validationError($errors);
            return;
        }

        $users = new UserRepository();
        $user = $users->findByEmail($clean['email']);

        if (!$user || !password_verify($clean['password'], $user['password'])) {
            Response::validationError(['email' => ['Identifiants invalides.']]);
            return;
        }
        if ((int) $user['is_blocked'] === 1) {
            Response::validationError(['email' => ['Ce compte est bloque.']]);
            return;
        }

        $token = (new TokenService())->createForUser((int) $user['id'], 'api');
        Response::json([
            'user' => $users->payload($user, (int) Bootstrap::config('min_age', 18)),
            'token' => $token,
            'phone_verified' => $users->hasVerifiedPhone($user),
        ]);
    }

    public function me(Request $request): void
    {
        $user = $request->user();
        if (!$user) {
            Response::error('Unauthorized', 401);
            return;
        }
        $users = new UserRepository();
        Response::json([
            'user' => $users->payload($user, (int) Bootstrap::config('min_age', 18)),
            'phone_verified' => $users->hasVerifiedPhone($user),
        ]);
    }

    public function logout(Request $request): void
    {
        $user = $request->user();
        if (!$user || empty($user['_token'])) {
            Response::error('Unauthorized', 401);
            return;
        }
        (new TokenService())->revoke($user['_token']);
        Response::json(['message' => 'Deconnecte.']);
    }
}
