<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\MenuItemController;
use App\Http\Controllers\IngredientController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\MenuItemAvailabilityController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderItemController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\DiscountController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::get('/me', [AuthController::class, 'me'])->middleware('auth:sanctum');

Route::post('/forgot-password', [AuthController::class, 'sendResetToken']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

Route::middleware(['auth:sanctum', 'role:Super Admin,Admin'])->prefix('users')->group(function () {
    Route::get('/', [UserController::class, 'index']);        // List all users
    Route::post('/', [UserController::class, 'store']);        // Create new user
    Route::put('/{user}', [UserController::class, 'update']);  // Update user
    Route::delete('/{user}', [UserController::class, 'destroy']); // Delete user
});
Route::middleware('auth:sanctum')->prefix('profile')->group(function () {
    Route::get('/', [ProfileController::class, 'show']);
    Route::put('/', [ProfileController::class, 'update']);
    Route::post('/avatar', [ProfileController::class, 'updateAvatar']);
});

Route::middleware(['auth:sanctum', 'role:Super Admin,Admin'])->prefix('categories')->group(function () {
    Route::get('/', [CategoryController::class, 'index']);
    Route::post('/', [CategoryController::class, 'store']);
    Route::get('/{category}', [CategoryController::class, 'show']);
    Route::put('/{category}', [CategoryController::class, 'update']);
    Route::delete('/{category}', [CategoryController::class, 'destroy']);
});


Route::middleware(['auth:sanctum', 'role:Super Admin,Admin'])->prefix('menu-items')->group(function () {
    Route::get('/', [MenuItemController::class, 'index']);
    Route::post('/', [MenuItemController::class, 'store']);
    Route::get('/{menuItem}', [MenuItemController::class, 'show']);
    Route::put('/{menuItem}', [MenuItemController::class, 'update']);
    Route::delete('/{menuItem}', [MenuItemController::class, 'destroy']);
});
Route::get('/menu-items/search', [MenuItemController::class, 'search']);
Route::middleware('auth:sanctum')->get('/menu-items/{id}/availability', [MenuItemAvailabilityController::class, 'check']);

// ✅ Update authenticated current user's name, password, role & email
Route::middleware('auth:sanctum')->put('/me', [UserController::class, 'updateMe']);
Route::middleware('auth:sanctum')->post('/profile/password', [ProfileController::class, 'updatePassword']);
Route::middleware('auth:sanctum')->get('/me', function () {
    return response()->json(auth()->user()->load('role'));
});


Route::middleware(['auth:sanctum', 'role:Super Admin,Admin'])->prefix('ingredients')->group(function () {
    Route::get('/', [IngredientController::class, 'index']);
    Route::post('/', [IngredientController::class, 'store']);
    Route::get('/{ingredient}', [IngredientController::class, 'show']);
    Route::put('/{ingredient}', [IngredientController::class, 'update']);
    Route::delete('/{ingredient}', [IngredientController::class, 'destroy']);
});

Route::middleware(['auth:sanctum', 'role:Super Admin,Admin'])->group(function () {
    Route::apiResource('ingredients', IngredientController::class);

    Route::prefix('stocks')->group(function () {
        Route::get('/', [StockController::class, 'index']);
        Route::post('/', [StockController::class, 'store']);
        Route::get('/{stock}', [StockController::class, 'show']);
    });
});
Route::middleware('auth:sanctum')->get('/orders/export', [OrderController::class, 'export']);


Route::middleware(['auth:sanctum', 'role:Super Admin,Admin'])->prefix('recipes')->group(function () {
    Route::get('/', [RecipeController::class, 'index']);
    Route::post('/', [RecipeController::class, 'store']);
    Route::delete('/{recipe}', [RecipeController::class, 'destroy']);
});
Route::middleware('auth:sanctum')->prefix('orders')->group(function () {
    Route::get('/', [OrderController::class, 'index']);
    Route::get('/{order}', [OrderController::class, 'show']);
    Route::put('/{order}', [OrderController::class, 'update']);
    Route::post('/', [OrderController::class, 'store']);
    Route::post('/{order}/pay', [OrderController::class, 'pay']);
    Route::post('/{order}/cancel', [OrderController::class, 'cancel']);
});

Route::middleware(['auth:sanctum', 'role:Super Admin,Admin'])->prefix('order-items')->group(function () {
    Route::get('/', [OrderItemController::class, 'index']);
    Route::get('/{orderItem}', [OrderItemController::class, 'show']);
    Route::delete('/{orderItem}', [OrderItemController::class, 'destroy']);
});

Route::middleware(['auth:sanctum', 'role:Super Admin,Admin'])->get('/dashboard-overview', [DashboardController::class, 'overview']);

Route::middleware(['auth:sanctum', 'role:Super Admin,Admin'])->prefix('reports')->group(function () {
    Route::get('/summary', [ReportController::class, 'summary']);
    Route::get('/top-items', [ReportController::class, 'topItems']);
    Route::get('/stats', [ReportController::class, 'stats']);
        // ✅ Add this new route
    Route::get('/monthly-revenue', [ReportController::class, 'monthlyRevenue']);
    Route::get('/revenue', [ReportController::class, 'revenueByFilter']);
});


Route::middleware('auth:sanctum')->prefix('discounts')->group(function () {
    Route::get('/validate', [DiscountController::class, 'validateCode']);
    Route::get('/', [DiscountController::class, 'index']);
    Route::post('/', [DiscountController::class, 'store']);
    Route::get('/{discount}', [DiscountController::class, 'show']);
    Route::put('/{discount}', [DiscountController::class, 'update']);
    Route::delete('/{discount}', [DiscountController::class, 'destroy']);
});


Route::middleware('auth:sanctum')->prefix('payments')->group(function () {
    Route::get('/', [PaymentController::class, 'index']);
    Route::post('/', [PaymentController::class, 'store']);
    Route::get('/{payment}', [PaymentController::class, 'show']);
    Route::post('/{payment}/logs', [PaymentController::class, 'log']);
});
Route::post('/orders/ai-reorder', [OrderController::class, 'aiReorder'])->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->prefix('notifications')->group(function () {
    Route::get('/', [NotificationController::class, 'index']);
    Route::post('/', [NotificationController::class, 'store']);
    Route::put('/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::delete('/{notification}', [NotificationController::class, 'destroy']);
});


use Illuminate\Http\Request;

Route::get('/ping', function () {
    return response()->json(['message' => 'pong']);
});

