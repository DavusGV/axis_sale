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
use App\Http\Controllers\EstablecimientoController;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\Finanzas\IngresosControlador;
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

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum')->name('logout');


// Rutas en api.php
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::middleware(['auth:sanctum', 'validate.establishment'])->group(function () {


    Route::prefix('roles-permissions')->group(function () {
        Route::post('/roles', [RolePermissionController::class, 'createRole']);
        Route::post('/permissions', [RolePermissionController::class, 'createPermission']);
        Route::post('/assign-permissions', [RolePermissionController::class, 'assignPermissionsToRole']);
        Route::post('/assign-role', [RolePermissionController::class, 'assignRoleToUser']);
    });
    #region Buildings Routes demo
        Route::prefix('edificios')->group(function () {
            Route::get('/', [BuildingsController::class, 'index']);
            Route::post('/', [BuildingsController::class, 'store']);
            Route::put('/{id}', [BuildingsController::class, 'update']);
            Route::delete('/{id}', [BuildingsController::class, 'destroy']);
        });

        Route::prefix('category')->group(function () {
            Route::get('/', [CategoryController::class, 'index']);
        });

    #endregion Buildings Routes demo


    Route::prefix('ventas')->group(function () {
        Route::get('/products', [VentasController::class, 'index']);
        Route::post('/store', [VentasController::class, 'store']);
        Route::post('/read-code', [VentasController::class, 'leerCodigoBarras']);
    });

    Route::prefix('reportes')->group(function () {
        Route::post('/ventas', [ReportesController::class, 'ventasReport']);
    });

     Route::prefix('products')->group(function () {
        Route::get('/', [ProductsController::class, 'index']);
        Route::post('/', [ProductsController::class, 'store']);
        Route::put('/{id}', [ProductsController::class, 'update']);
        Route::delete('/{id}', [ProductsController::class, 'destroy']);
    });

    Route::prefix('establecimientos')->group(function () {
        Route::get('/', [EstablecimientoController::class, 'index']);
        Route::post('/', [EstablecimientoController::class, 'store']);
        Route::get('{id}', [EstablecimientoController::class, 'show']);
        Route::put('{id}', [EstablecimientoController::class, 'update']);
        Route::delete('{id}', [EstablecimientoController::class, 'destroy']);
    });

    Route::prefix('users')->group(function () {

        Route::get('/', [UsersController::class, 'index']);
        Route::post('/', [UsersController::class, 'store']);
        Route::get('{id}', [UsersController::class, 'show']);
        Route::put('{id}', [UsersController::class, 'update']);
        Route::delete('{id}', [UsersController::class, 'destroy']);
        Route::get('{id}/establecimientos', [UsersController::class, 'establecimientos']);
        Route::post('{id}/establecimientos', [UsersController::class, 'assignEstablecimiento']);
        Route::delete('{id}/establecimientos/{establecimientoId}', [UsersController::class, 'unassignEstablecimiento']);
    });

    Route::prefix('cajas')->group(function () {
        Route::get('/', [CajasController::class, 'index']);
        Route::get('/{boxId}/history', [CajasController::class, 'showHistoryBox']);
        Route::get('/{historyId}/ventas', [CajasController::class, 'showHistorySale']);
        Route::post('open', [CajasController::class, 'open']);
        Route::post('close', [CajasController::class, 'close']);
    });


     Route::prefix('finance')->group(function () {
        Route::post('/getIncome', [IngresosControlador::class, 'getIncome']);
    });
});


