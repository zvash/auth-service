<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    return $router->app->version();
});

$router->get('/who', function () use ($router) {
    return "Auth Service";
});

$router->post('/password/forget', 'Api\V1\UserController@forgetPassword');
$router->get('/password/forget/{token}', 'Api\V1\UserController@resetPasswordForm');
$router->post('/password/reset/{token}', 'Api\V1\UserController@resetPassword');

$router->group(['prefix' => 'api/v1'], function ($router) {

    $router->post('login-passport', '\Laravel\Passport\Http\Controllers\AccessTokenController@issueToken');

    $router->group(['namespace' => 'Api\V1'], function ($router) {

        $router->post('login', 'AccessTokenController@issueToken');

        $router->get('countries', 'CountryController@getAll');

        $router->post('code-request', 'UserController@register');
        //$router->post('code-request', 'UserController@codeRequest');

        /** Authenticated Users Only */
        $router->group(['middleware' => 'auth'], function ($router) {
            $router->get('logout', 'UserController@logout');
            $router->post('logout', 'UserController@logout');

            $router->get('authenticate', 'UserController@authenticate');

            $router->post('profile/update', 'UserController@update');
            $router->post('profile/remove-image', 'UserController@deleteProfileImage');
            $router->get('profile/status/completion', 'UserController@profileStatus');
            $router->get('profile/status/referrals', 'UserController@referralSummary');
            $router->get('profile/status/referrals/statistics', 'UserController@referralStatistics');

            $router->get('configs/refer-coins', 'ConfigController@getReferralCoinAmount');
            $router->get('configs/coins', 'ConfigController@get');

            $router->post('player/register', 'PlayerController@registerPlayerId');

            $router->get('notifications', 'UserController@notifications');

            $router->post('profile/iban', 'UserController@setIBan');
            $router->get('profile/iban', 'UserController@getIBan');
        });

        $router->group(['middleware' => 'admin'], function ($router) {
            $router->post('admin/password/change', 'UserController@changePassword');
            $router->post('admin/register', 'UserController@registerAdmin');

            $router->post('admin/configs', 'ConfigController@set');
            $router->put('admin/configs/{key}/set', 'ConfigController@update');
            $router->get('admin/configs/all', 'ConfigController@all');

            $router->post('admin/services/register', 'ServiceController@register');
            $router->get('admin/services/all', 'ServiceController@getAll');
        });

        $router->group(['middleware' => 'trusted'], function ($router) {

            $router->get('users/{userId}', 'UserController@getUserById');
            $router->post('users/{userId}/complete-task', 'UserController@completeTask');

        });
    });

});

$router->group(['prefix' => 'storage'], function ($router) {

    $router->group(['namespace' => 'Resource'], function ($router) {

        $router->group(['prefix' => 'images'], function ($router) {

            $router->get('/{fileName}', 'ImagesController@download');

        });
    });

});

