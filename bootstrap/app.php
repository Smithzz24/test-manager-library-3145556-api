<?php

use App\Domain\RuleViolation;
use App\Infrastructure\Console\ProvisionAdmin;
use App\Infrastructure\Http\Authenticate;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
    )
    ->withCommands([ProvisionAdmin::class])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['jwt' => Authenticate::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->dontFlash(['password', 'password_confirmation', 'current_password']);
        $exceptions->report(function (Throwable $error): void {
            // Log the class, not SQL bindings, credentials, tokens, or request bodies.
            if (! $error instanceof RuleViolation && ! $error instanceof ValidationException && ! $error instanceof HttpExceptionInterface) {
                Log::error('API operation failed.', ['exception_type' => $error::class]);
            }
        })->stop();
        $exceptions->render(function (Throwable $error, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }
            if ($error instanceof RuleViolation) {
                $status = match ($error->errorCode) {
                    'INVALID_CREDENTIALS', 'UNAUTHENTICATED' => 401,
                    'FORBIDDEN_ROLE' => 403,
                    'NOT_FOUND' => 404,
                    'VALIDATION_ERROR', 'MEMBER_LIMIT_EXCEEDED', 'DUPLICATE_BOOK_IN_LOAN', 'INVALID_DATE_RANGE' => 422,
                    default => 409,
                };

                return response()->json(['code' => $error->errorCode, 'message' => $error->getMessage(), 'details' => []], $status);
            }
            if ($error instanceof ValidationException) {
                $details = [];
                foreach ($error->errors() as $field => $messages) {
                    foreach ($messages as $message) {
                        $details[] = ['field' => $field, 'issue' => $message];
                    }
                }

                return response()->json(['code' => 'VALIDATION_ERROR', 'message' => 'Revisa los datos enviados.', 'details' => $details], 422);
            }
            if ($error instanceof HttpExceptionInterface && $error->getStatusCode() < 500) {
                return response()->json(['code' => 'NOT_FOUND', 'message' => 'La solicitud no está disponible.', 'details' => []], $error->getStatusCode());
            }

            return response()->json(['code' => 'INTERNAL_ERROR', 'message' => 'No se pudo completar la solicitud.', 'details' => []], 500);
        });
    })->create();
