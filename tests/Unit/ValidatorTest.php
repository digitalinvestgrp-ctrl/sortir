<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Validator;
use PHPUnit\Framework\TestCase;

class ValidatorTest extends TestCase
{
    private function v(): Validator
    {
        // Mock PDO non utilise (pas de regle unique:)
        $pdo = $this->createMock(\PDO::class);
        return new Validator($pdo);
    }

    public function testRequiredFails(): void
    {
        [$ok, $errors] = $this->v()->check([], ['email' => 'required|email']);
        $this->assertFalse($ok);
        $this->assertArrayHasKey('email', $errors);
    }

    public function testEmailRule(): void
    {
        [$ok] = $this->v()->check(['email' => 'not-an-email'], ['email' => 'required|email']);
        $this->assertFalse($ok);
    }

    public function testIntegerBetween(): void
    {
        [$ok] = $this->v()->check(['lat' => 50], ['lat' => 'required|numeric|between:-90,90']);
        $this->assertTrue($ok);
        [$ok] = $this->v()->check(['lat' => 200], ['lat' => 'required|numeric|between:-90,90']);
        $this->assertFalse($ok);
    }

    public function testRegexPhone(): void
    {
        $rule = ['phone' => 'required|regex:/^\+[1-9]\d{7,14}$/'];
        $this->assertTrue($this->v()->check(['phone' => '+33612345678'], $rule)[0]);
        $this->assertFalse($this->v()->check(['phone' => '0612345678'], $rule)[0]);
    }

    public function testConfirmedPassword(): void
    {
        $rule = ['password' => 'required|confirmed|min:8'];
        [$ok] = $this->v()->check(['password' => 'abcd1234', 'password_confirmation' => 'abcd1234'], $rule);
        $this->assertTrue($ok);
        [$ok] = $this->v()->check(['password' => 'abcd1234', 'password_confirmation' => 'X'], $rule);
        $this->assertFalse($ok);
    }
}
