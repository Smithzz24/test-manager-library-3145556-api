<?php

use App\Infrastructure\Http\AuthController;
use App\Infrastructure\Http\BookController;
use App\Infrastructure\Http\HealthController;
use App\Infrastructure\Http\LoanController;
use App\Infrastructure\Http\MemberController;
use App\Infrastructure\Http\ReportController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class)->name('health');
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('jwt')->group(function (): void {
    Route::get('/categories', [BookController::class, 'categories']);
    Route::get('/books', [BookController::class, 'index']);
    Route::get('/books/{id}', [BookController::class, 'show'])->whereUuid('id');
    Route::get('/members', [MemberController::class, 'index']);
    Route::post('/members', [MemberController::class, 'create']);
    Route::get('/members/{id}', [MemberController::class, 'show'])->whereUuid('id');
    Route::get('/loans', [LoanController::class, 'index']);
    Route::post('/loans', [LoanController::class, 'create']);
    Route::get('/loans/{id}', [LoanController::class, 'show'])->whereUuid('id');
    Route::post('/loans/{loanId}/items/{itemId}/return', [LoanController::class, 'returnItem'])->whereUuid(['loanId', 'itemId']);
});

Route::middleware('jwt:admin')->group(function (): void {
    Route::post('/users', [AuthController::class, 'create']);
    Route::post('/books', [BookController::class, 'create']);
    Route::put('/books/{id}', [BookController::class, 'update'])->whereUuid('id');
    Route::delete('/books/{id}', [BookController::class, 'delete'])->whereUuid('id');
    Route::get('/reports/most-borrowed', ReportController::class);
});
