<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        // Improve API error responses
        $this->renderable(function (Throwable $e, $request) {
            if ($request->is('api/*') || $request->wantsJson()) {
                // Handle validation errors
                if ($e instanceof \Illuminate\Validation\ValidationException) {
                    return response()->json([
                        'message' => 'Validation failed',
                        'errors' => $e->errors(),
                    ], 422);
                }

                // Handle model not found errors
                if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                    $modelName = strtolower(class_basename($e->getModel()));
                    return response()->json([
                        'message' => "The requested {$modelName} was not found.",
                        'error' => $e->getMessage()
                    ], 404);
                }

                // Handle database errors
                if ($e instanceof \Illuminate\Database\QueryException) {
                    $errorMessage = $e->getMessage();
                    $sqlStateMatch = [];
                    // Extract SQL state code if possible
                    preg_match('/SQLSTATE\[(\w+)\]/', $errorMessage, $sqlStateMatch);
                    $sqlState = $sqlStateMatch[1] ?? 'UNKNOWN';

                    return response()->json([
                        'message' => 'Database error occurred',
                        'error' => $errorMessage,
                        'sql_state' => $sqlState
                    ], 500);
                }

                // For other errors, provide a consistent response format
                $statusCode = $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
                    ? $e->getStatusCode()
                    : 500;

                return response()->json([
                    'message' => $e->getMessage() ?: 'An unexpected error occurred',
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                    'trace' => config('app.debug') ? $e->getTrace() : null,
                ], $statusCode);
            }

            return null; // Let Laravel handle non-API exceptions normally
        });
    }
}
