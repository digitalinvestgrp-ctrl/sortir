<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Validator minimaliste (pattern Agendia) — remplace Request::validate Laravel
 *
 * Rules supportees :
 *   required, string, integer, numeric, email, max:N, min:N, between:A,B,
 *   regex:/pattern/, in:a,b,c, date, before_today, confirmed, unique:table,column
 */
class Validator
{
    private \PDO $pdo;

    public function __construct(?\PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Pdo::instance();
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,string[]|string> $rules
     * @return array{0:bool, 1:array<string,string[]>, 2:array<string,mixed>}
     */
    public function check(array $data, array $rules): array
    {
        $errors = [];
        $clean = [];

        foreach ($rules as $field => $ruleSet) {
            $list = is_array($ruleSet) ? $ruleSet : explode('|', $ruleSet);
            $value = $data[$field] ?? null;
            $nullable = in_array('nullable', $list, true);
            $required = in_array('required', $list, true);

            if (($value === null || $value === '') && !$required) {
                if (!$nullable && array_key_exists($field, $data)) {
                    $clean[$field] = $value;
                }
                continue;
            }

            if ($required && ($value === null || $value === '')) {
                $errors[$field][] = "Le champ {$field} est requis.";
                continue;
            }

            foreach ($list as $rule) {
                if ($rule === 'required' || $rule === 'nullable') {
                    continue;
                }
                if ($rule === 'string' && !is_string($value)) {
                    $errors[$field][] = "{$field} doit etre une chaine.";
                    continue;
                }
                if ($rule === 'integer' && filter_var($value, FILTER_VALIDATE_INT) === false) {
                    $errors[$field][] = "{$field} doit etre un entier.";
                    continue;
                }
                if ($rule === 'numeric' && !is_numeric($value)) {
                    $errors[$field][] = "{$field} doit etre numerique.";
                    continue;
                }
                if ($rule === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$field][] = "{$field} doit etre un email valide.";
                    continue;
                }
                if ($rule === 'date' && !$this->isDate($value)) {
                    $errors[$field][] = "{$field} doit etre une date valide.";
                    continue;
                }
                if ($rule === 'before_today' && $this->isDate($value) && strtotime($value) >= strtotime(date('Y-m-d'))) {
                    $errors[$field][] = "{$field} doit etre avant aujourd'hui.";
                    continue;
                }
                if (str_starts_with($rule, 'max:')) {
                    $max = (int) substr($rule, 4);
                    if (is_string($value) && mb_strlen($value) > $max) {
                        $errors[$field][] = "{$field} max {$max} caracteres.";
                    } elseif (is_numeric($value) && (float) $value > $max) {
                        $errors[$field][] = "{$field} max {$max}.";
                    }
                    continue;
                }
                if (str_starts_with($rule, 'min:')) {
                    $min = (int) substr($rule, 4);
                    if (is_string($value) && mb_strlen($value) < $min) {
                        $errors[$field][] = "{$field} min {$min} caracteres.";
                    } elseif (is_numeric($value) && (float) $value < $min) {
                        $errors[$field][] = "{$field} min {$min}.";
                    }
                    continue;
                }
                if (str_starts_with($rule, 'between:')) {
                    [$a, $b] = explode(',', substr($rule, 8));
                    if (is_numeric($value) && ((float)$value < (float)$a || (float)$value > (float)$b)) {
                        $errors[$field][] = "{$field} entre {$a} et {$b}.";
                    }
                    continue;
                }
                if (str_starts_with($rule, 'regex:')) {
                    $pattern = substr($rule, 6);
                    if (!preg_match($pattern, (string) $value)) {
                        $errors[$field][] = "{$field} format invalide.";
                    }
                    continue;
                }
                if (str_starts_with($rule, 'in:')) {
                    $allowed = explode(',', substr($rule, 3));
                    if (!in_array((string) $value, $allowed, true)) {
                        $errors[$field][] = "{$field} doit etre dans : " . implode(', ', $allowed);
                    }
                    continue;
                }
                if ($rule === 'confirmed') {
                    $confirm = $data[$field . '_confirmation'] ?? null;
                    if ($confirm !== $value) {
                        $errors[$field][] = "{$field} ne correspond pas a la confirmation.";
                    }
                    continue;
                }
                if (str_starts_with($rule, 'unique:')) {
                    $parts = explode(',', substr($rule, 7));
                    $table = $parts[0];
                    $col = $parts[1] ?? $field;
                    $stmt = $this->pdo->prepare("SELECT 1 FROM `{$table}` WHERE `{$col}` = ? LIMIT 1");
                    $stmt->execute([$value]);
                    if ($stmt->fetchColumn()) {
                        $errors[$field][] = "{$field} doit etre unique.";
                    }
                    continue;
                }
            }

            if (!isset($errors[$field])) {
                $clean[$field] = $value;
            }
        }

        return [empty($errors), $errors, $clean];
    }

    private function isDate(string $value): bool
    {
        $ts = strtotime($value);
        if ($ts === false) {
            return false;
        }
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}/', $value);
    }
}
