<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\BuildingsController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductsController;
use App\Http\Controllers\CajasController;
use App\Http\Controllers\VentasController;
use App\Http\Controllers\ReportesController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Rutas en api.php
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::prefix('roles-permissions')->group(function () {
        Route::post('/roles', [RolePermissionController::class, 'createRole']);
        Route::post('/permissions', [RolePermissionController::class, 'createPermission']);
        Route::post('/assign-permissions', [RolePermissionController::class, 'assignPermissionsToRole']);
        Route::post('/assign-role', [RolePermissionController::class, 'assignRoleToUser']);
    });

    Route::prefix('edificios')->group(function () {
        Route::get('/', [BuildingsController::class, 'index']);
        Route::post('/', [BuildingsController::class, 'store']);
        Route::put('/{id}', [BuildingsController::class, 'update']);
        Route::delete('/{id}', [BuildingsController::class, 'destroy']);
    });

    Route::prefix('category')->group(function () {
        Route::get('/', [CategoryController::class, 'index']);
    });

    Route::prefix('products')->group(function () {
        Route::get('/', [ProductsController::class, 'index']);
        Route::post('/', [ProductsController::class, 'store']);
        Route::put('/{id}', [ProductsController::class, 'update']);
    });

    Route::prefix('cajas')->group(function () {
        Route::get('/', [CajasController::class, 'index']);
        Route::post('open', [CajasController::class, 'open']);
        Route::post('close', [CajasController::class, 'close']);
    });

    Route::prefix('ventas')->group(function () {
        Route::post('/store', [VentasController::class, 'store']);
    });

    Route::prefix('reportes')->group(function () {
        Route::post('/ventas', [ReportesController::class, 'ventasReport']);
    });


});


