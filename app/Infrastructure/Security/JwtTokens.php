<?php

namespace App\Infrastructure\Security;

use App\Application\Ports\Clock;
use App\Application\Ports\Tokens;
use App\Domain\Operator;
use App\Domain\RuleViolation;
use DomainException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use InvalidArgumentException;
use UnexpectedValueException;

final readonly class JwtTokens implements Tokens
{
    public function __construct(private Clock $clock, #[\SensitiveParameter] private string $secret)
    {
        if (strlen($secret) < 32) {
            throw new InvalidArgumentException('JWT_SECRET must contain at least 32 bytes.');
        }
    }

    public function issue(Operator $user): string
    {
        $now = $this->clock->now()->getTimestamp();

        return JWT::encode(['sub' => $user->id, 'role' => $user->role, 'iat' => $now, 'exp' => $now + 28800], $this->secret, 'HS256');
    }

    public function verify(#[\SensitiveParameter] string $token): array
    {
        $previous = JWT::$timestamp;
        JWT::$timestamp = $this->clock->now()->getTimestamp();
        try {
            $claims = JWT::decode($token, new Key($this->secret, 'HS256'));
        } catch (UnexpectedValueException|DomainException $error) {
            throw new RuleViolation('UNAUTHENTICATED', 'Debes iniciar sesión con un token válido.');
        } finally {
            JWT::$timestamp = $previous;
        }
        if (! isset($claims->sub, $claims->role, $claims->iat, $claims->exp)
            || ! is_string($claims->sub) || ! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/Di', $claims->sub)
            || ! in_array($claims->role, ['admin', 'librarian'], true)
            || ! is_int($claims->iat) || ! is_int($claims->exp) || $claims->exp - $claims->iat !== 28800) {
            throw new RuleViolation('UNAUTHENTICATED', 'Debes iniciar sesión con un token válido.');
        }

        return ['sub' => $claims->sub, 'role' => $claims->role];
    }
}
