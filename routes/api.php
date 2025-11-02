<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\MenuItemVariantController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\MenuItemController;
use App\Http\Controllers\IngredientController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderItemController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\DiscountController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\AIAnalyticsController;
use App\Http\Controllers\BannerController;
use App\Http\Controllers\BakongController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TableLoginController;
use App\Http\Controllers\TableController;

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
*/
Route::post('/table-login', [TableLoginController::class, 'loginBySlug']);
Route::get('/settings', [SettingsController::class, 'index']);

// ── Auth (public) ───────────────────────────────────────────────────────────
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);
Route::post('/auth/password/otp/verify', [AuthController::class, 'verifyOtp']);

// OTP password flows (both "forgot" and "register set password" use these)
Route::middleware('throttle:10,1')->group(function () {
    Route::post('/auth/password/otp/request', [AuthController::class, 'forgot']);        // { email }
    Route::post('/auth/password/otp/resend',  [AuthController::class, 'resendOtp']);     // { email }
    Route::post('/auth/password/otp/reset',   [AuthController::class, 'resetWithOtp']);  // { email, code, password, password_confirmation }
});

// Bakong (public callbacks + helpers)
Route::prefix('bakong')->group(function () {
    Route::get('/token',          [BakongController::class, 'getToken']);
    Route::post('/generate-qr',   [BakongController::class, 'generateQR']);
    Route::get('/check/{billNumber}', [BakongController::class, 'checkStatus']);
    Route::post('/pushback',      [BakongController::class, 'handlePushback']);   // webhook
    Route::get('/verify/md5',     [BakongController::class, 'verifyTransactionByMd5']);
    Route::get('/verify/bill',    [BakongController::class, 'verifyTransactionByBill']);
});

/*
|--------------------------------------------------------------------------
| Authenticated (all roles) — Sanctum
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    // Session
    Route::post('/logout', [AuthController::class, 'logout']);

    // Current user
    Route::get('/me',  [AuthController::class, 'me']);       // ← single source of truth for /me
    Route::put('/me',  [UserController::class, 'updateMe']); // name/email
    Route::put('/users/me/password', [UserController::class, 'updateMyPassword']); // ← NEW self password

    // Profile (keep if your frontend uses these)
    Route::prefix('profile')->group(function () {
        Route::get('/',          [ProfileController::class, 'show']);
        Route::put('/',          [ProfileController::class, 'update']);
        Route::post('/avatar',   [ProfileController::class, 'updateAvatar']);
        Route::post('/password', [ProfileController::class, 'updatePassword']);
    });

    // Banners (if these should be admin-only, wrap with role middleware)
    Route::prefix('banners')->group(function () {
        Route::get('/',            [BannerController::class, 'index']);
        Route::post('/',           [BannerController::class, 'store']);
        Route::put('/{banner}',    [BannerController::class, 'update']);
        Route::delete('/{banner}', [BannerController::class, 'destroy']);
        Route::put('/reorder',     [BannerController::class, 'reorder']);
    });

    // Orders
    Route::get('/orders/export', [OrderController::class, 'export']);
    Route::prefix('orders')->group(function () {
        Route::get('/',                 [OrderController::class, 'index']);
        Route::get('/{order}',          [OrderController::class, 'show']);
        Route::put('/{order}',          [OrderController::class, 'update']);
        Route::post('/',                [OrderController::class, 'store']);
        Route::post('/{order}/pay',     [OrderController::class, 'pay']);
        Route::post('/{order}/cancel',  [OrderController::class, 'cancel']);
        Route::post('/ai-reorder',      [OrderController::class, 'aiReorder']);
        Route::post('/{order}/pay-cash',[OrderController::class, 'payCash']);
    });

    // Order items
    Route::prefix('order-items')->group(function () {
        Route::post('/',             [OrderItemController::class, 'store']);
        Route::put('/{orderItem}',   [OrderItemController::class, 'update']);
        Route::delete('/{orderItem}',[OrderItemController::class, 'destroy']);
    });

    // Categories — read for all, write for staff
    Route::prefix('categories')->group(function () {
        Route::get('/',           [CategoryController::class, 'index']);
        Route::get('/{category}', [CategoryController::class, 'show']);
    });
    Route::middleware('role:Super Admin,Admin,Cashier')->group(function () {
        Route::prefix('categories')->group(function () {
            Route::post('/',             [CategoryController::class, 'store']);
            Route::put('/{category}',    [CategoryController::class, 'update']);
            Route::delete('/{category}', [CategoryController::class, 'destroy']);
            Route::post('/{id}/restore', [CategoryController::class, 'restore']);
        });
        Route::post('/settings', [SettingsController::class, 'update']);
    });


    // Menu items — read for all, write for staff
    Route::prefix('menu-items')->group(function () {
        Route::get('/',           [MenuItemController::class, 'index']);
        Route::get('/{menuItem}', [MenuItemController::class, 'show']);
        Route::get('/search',     [MenuItemController::class, 'search']);
    });
    Route::middleware('role:Super Admin,Admin,Cashier')->group(function () {
        Route::prefix('menu-items')->group(function () {
            Route::post('/',             [MenuItemController::class, 'store']);
            Route::put('/{menuItem}',    [MenuItemController::class, 'update']);
            Route::delete('/{menuItem}', [MenuItemController::class, 'destroy']);
            Route::post('/{id}/restore', [MenuItemController::class, 'restore']);
        });
    });

    // Discounts
    Route::prefix('discounts')->group(function () {
        Route::get('/validate', [DiscountController::class, 'validateCode']);
        Route::get('/',         [DiscountController::class, 'index']);
        Route::post('/',        [DiscountController::class, 'store']);
        Route::get('/{discount}', [DiscountController::class, 'show']);
        Route::put('/{discount}', [DiscountController::class, 'update']);
        Route::delete('/{discount}', [DiscountController::class, 'destroy']);
    });

    // Payments
    Route::prefix('payments')->group(function () {
        Route::get('/',           [PaymentController::class, 'index']);
        Route::post('/',          [PaymentController::class, 'store']);
        Route::get('/{payment}',  [PaymentController::class, 'show']);
        Route::post('/{payment}/logs', [PaymentController::class, 'log']);
    });

    // Notifications
    Route::prefix('notifications')->group(function () {
        Route::get('/',                    [NotificationController::class, 'index']);
        Route::get('/active',              [NotificationController::class, 'active']);
        Route::post('/',                   [NotificationController::class, 'store']);
        Route::put('/{notification}',      [NotificationController::class, 'update']);
        Route::delete('/{notification}',   [NotificationController::class, 'destroy']);
        Route::put('/{notification}/read', [NotificationController::class, 'markAsRead']);
    });
// Variants (SHALLOW for update/destroy; nested for index/store; custom nested restore)
    Route::apiResource('menu-items.variants', MenuItemVariantController::class)->shallow();
    Route::post('menu-items/{menu_item}/variants/{variant}/restore', [MenuItemVariantController::class, 'restore']);
    /*
    |--------------------------------------------------------------------------
    | Staff routes — Super Admin, Admin, Cashier
    |--------------------------------------------------------------------------
    */
    Route::middleware('role:Super Admin,Admin,Cashier')->group(function () {
        // Read-only users list for POS/staff view (if used)
        Route::prefix('users/view')->group(function () {
            Route::get('/', [UserController::class, 'index']);
        });

        Route::get('/dashboard-overview', [DashboardController::class, 'overview']);

        // Reports (de-duplicated top-items)
        Route::prefix('reports')->group(function () {
            Route::get('/summary',          [ReportController::class, 'summary']);
            Route::get('/top-items',        [ReportController::class, 'topItems']);
            Route::get('/stats',            [ReportController::class, 'stats']);
            Route::get('/monthly-revenue',  [ReportController::class, 'monthlyRevenue']);
            Route::get('/revenue',          [ReportController::class, 'revenueByFilter']);
        });

        // Ingredients / Stocks / Recipes
        Route::apiResource('ingredients', IngredientController::class);

        Route::prefix('stocks')->group(function () {
            Route::get('/',      [StockController::class, 'index']);
            Route::post('/',     [StockController::class, 'store']);
            Route::get('/{stock}', [StockController::class, 'show']);
        });

        Route::prefix('recipes')->group(function () {
            Route::get('/',        [RecipeController::class, 'index']);
            Route::post('/',       [RecipeController::class, 'store']);
            Route::delete('/{recipe}', [RecipeController::class, 'destroy']);
        });

        // AI analytics
        Route::get('/analytics/sales-history',      [AIAnalyticsController::class, 'salesHistory']);
        Route::get('/analytics/inventory-history',  [AIAnalyticsController::class, 'inventoryHistory']);
        Route::get('/analytics/basket-history',     [AIAnalyticsController::class, 'basketHistory']);
        Route::get('/analytics/ingredient-stock',   [AIAnalyticsController::class, 'ingredientStock']);
    });

    /*
    |--------------------------------------------------------------------------
    | Management — Super Admin & Admin
    |--------------------------------------------------------------------------
    */
    Route::middleware('role:Super Admin,Admin')->group(function () {
        // Tables
        Route::apiResource('tables', TableController::class);

        // Users (management)
        Route::prefix('users')->group(function () {
            Route::get('/',           [UserController::class, 'index']);
            Route::post('/',          [UserController::class, 'store']);
            Route::put('/{user}',     [UserController::class, 'update']);
            Route::delete('/{user}',  [UserController::class, 'destroy']);
            Route::get('/trashed', [UserController::class, 'trashed']);          // list only soft-deleted
            Route::patch('/{id}/restore', [UserController::class, 'restore']);   // restore
            Route::delete('/{id}/force', [UserController::class, 'forceDestroy']);

            // NEW: admin resets another user's password
            Route::put('/{user}/password', [UserController::class, 'resetPassword']);
        });
    });
});
