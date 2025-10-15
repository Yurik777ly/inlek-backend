<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\CartController;
use App\Http\Controllers\API\CategoryController;
use App\Http\Controllers\API\ContentController;
use App\Http\Controllers\API\OrderController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\ProfileController;
use App\Http\Controllers\API\V2\SearchController;
use App\Http\Controllers\API\Push\NotificationController;

use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/request-code',        [AuthController::class, 'requestCode']);
    Route::post('/registration',        [AuthController::class, 'registration']);
    Route::post('/update-password',     [AuthController::class, 'updatePassword']);
    Route::post('/login',               [AuthController::class, 'login']);
    Route::post('/check',               [AuthController::class, 'checkPhone']);
});
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::get('/test',             [AuthController::class, 'test']);
        Route::post('/update-fcm-token',[AuthController::class, 'updateFcmToken']);
        Route::post('/logout',          [AuthController::class, 'logout']);
    });
    Route::prefix('profile')->group(function () {
        Route::get('/user',             [ProfileController::class, 'getProfile']);
        Route::put('/update',           [ProfileController::class, 'updateProfile']);
        Route::delete('/delete',        [ProfileController::class, 'deleteProfile']);
//        Route::get('/last-data',        [ProfileController::class, 'getLastData']);                //!!!
    });

    Route::prefix('order')->group(function () {
        Route::post('/',                [OrderController::class, 'prepareOrder']);                   //!!!
        Route::get('/history',          [OrderController::class, 'getOrders']);              //!!!
        Route::get('/statuses',         [OrderController::class, 'getOrderStatuses']);
        Route::get('/details/{orderId}',[OrderController::class, 'getDetailedOrder']);               //!!!
        Route::post('/{orderId}/repeat',[CartController::class, 'repeatOrder']);
    });

    Route::prefix('product')->group(function () {
        Route::get('/search',           [ProductController::class,  'getFilteredList']);
        Route::get('/daily',            [ProductController::class,  'getDaily']);
        Route::get('/{id}',             [ProductController::class,  'getById']);
        Route::get('/{id}/pharmacies',  [ProductController::class,  'getPharmaciesByProductId']);
        Route::post('/{id}/notification',[ProductController::class, 'addProductNotification']);  
    });

    Route::prefix('cart')->group(function () {
        Route::get('/',                 [CartController::class, 'getCart']);
        Route::get('/detailed',         [CartController::class, 'getCartDetailed']);
        Route::get('/pharmacies',       [CartController::class, 'getProductPharmacyCart']);
        Route::post('/add',             [CartController::class, 'addToCart']);
        Route::delete('/',              [CartController::class, 'clearCart']);
        Route::post('/promo',           [CartController::class, 'addPromocode']);                //!!!
        Route::delete('/promo',         [CartController::class, 'deletePromocode']);             //!!!
    });

    Route::prefix('v2/cart')->group(function () {
        Route::post('/pharmacies', [CartController::class, 'getProductPharmacyCartV2']);
    });
     /*
         Route::prefix('payments')->group(function () {
             Route::post('/card',            [PaymentController::class, 'card']);                    //!!!
             Route::post('/erip',            [PaymentController::class, 'erip']);                    //!!!
             Route::post('/oplati',          [PaymentController::class, 'oplati']);                  //!!!
         });
     */
//    Route::get('/search/history',          [SearchController::class,  'getHistory']);              //!!!
//    Route::post('product/add-notification',[ProductController::class, 'addProductNotification']);  //!!!

         Route::prefix('payments')->group(function () {
            Route::get('/', function(){var_dump(123);exit;});
            Route::get('/payments-status', [OrderController::class, 'paymentStatus']);
            Route::get('/payment-success', [OrderController::class, 'payment']);
            Route::post('/payment-success', [OrderController::class, 'payment']);
            Route::get('/payment-failed', [OrderController::class, 'payment']);
            Route::post('/payment-failed', [OrderController::class, 'payment']);
            Route::get('/payment-process', [OrderController::class, 'payment']);
            Route::post('/payment-process', [OrderController::class, 'payment']);
         });

});

Route::prefix('categories')->group(function () {
    Route::get('/',                     [CategoryController::class, 'getList']);                    //!!!
    Route::get('/{id}',                 [CategoryController::class, 'getList']);                   //!!!
    Route::get('/{id}/forms',           [CategoryController::class, 'getReleaseForms']);                //!!!
    Route::get('/{id}/brands',          [CategoryController::class, 'getBrands']);               //!!!
    Route::get('/{id}/countries',       [CategoryController::class, 'getCountries']);                    //!!!
});

Route::get('/v2/search', [SearchController::class, 'search']);
/*
Route::prefix('search')->group(function () {
    Route::post('/',                [SearchController::class,   'doSearch']);                         //!!!
    Route::get('/popular',          [SearchController::class,   'getPopular']);                       //!!!
});
*/

Route::get('/actions',              [ContentController::class,  'getActions']);
Route::get('/actions/{id}',         [ContentController::class,	'getActionById']);
Route::get('/news',                 [ContentController::class,  'getNews']);
Route::get('/news/{id}',            [ContentController::class,  'getNewsById']);
Route::get('/articles',             [ContentController::class,  'getArticles']);
Route::get('/articles/{id}',        [ContentController::class,  'getArticleById']);
Route::get('/about',                [ContentController::class,  'about']);
Route::get('/banners',              [ContentController::class,  'getBanners']);
Route::get('/pharmacies',           [ContentController::class,  'getPharmacies']);
Route::get('/cities',               [ContentController::class,  'getCities']);
Route::get('/info',                 [ContentController::class,  'getCustomerInfo']);

Route::prefix('notifications')->group(function () {
    Route::post('/send', [NotificationController::class, 'sendNotification']);
    Route::get('/token', [NotificationController::class, 'getAccessToken']);
});
