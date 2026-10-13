<?php

namespace App\Infrastructure\Http;

use App\Application\Ports\Tokens;
use App\Application\Ports\Users;
use App\Domain\RuleViolation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class Authenticate
{
    public function __construct(private Tokens $tokens, private Users $users) {}

    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $token = $request->bearerToken();
        if ($token === null) {
            throw new RuleViolation('UNAUTHENTICATED', 'Debes iniciar sesión con un token válido.');
        }
        $claims = $this->tokens->verify($token);
        $user = $this->users->byId($claims['sub']);
        if ($user === null || $user->role !== $claims['role']) {
            throw new RuleViolation('UNAUTHENTICATED', 'Debes iniciar sesión con un token válido.');
        }
        if ($roles !== [] && ! in_array($user->role, $roles, true)) {
            throw new RuleViolation('FORBIDDEN_ROLE', 'No tienes permiso para realizar esta operación.');
        }
        $request->attributes->set('operator_id', $user->id);

        return $next($request);
    }
}
