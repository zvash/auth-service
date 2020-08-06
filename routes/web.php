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

    $router->post('login', '\Laravel\Passport\Http\Controllers\AccessTokenController@issueToken');

    $router->group(['namespace' => 'Api\V1'], function ($router) {

        $router->get('countries', 'CountryController@getAll');

        $router->post('code-request', 'UserController@register');
        //$router->post('code-request', 'UserController@codeRequest');

        /** Authenticated Users Only */
        $router->group(['middleware' => 'auth'], function ($router) {
            $router->get('logout', 'UserController@logout');
            $router->get('authenticate', 'UserController@authenticate');
            //$router->get('me', 'UserController@getUser');

            $router->post('profile/update', 'UserController@update');
            $router->post('profile/remove-image', 'UserController@deleteProfileImage');
        });

        $router->group(['middleware' => 'admin'], function ($router) {
            $router->post('admin/password/change', 'UserController@changePassword');
            $router->post('admin/register', 'UserController@registerAdmin');
        });
    });

});

