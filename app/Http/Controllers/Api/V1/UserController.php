<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\NotificationService;
use App\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Passport\Client;

class UserController extends Controller
{

    /**
     * @param Request $request
     * @param NotificationService $notificationService
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function register(Request $request, NotificationService $notificationService)
    {
        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'unique:users'],
            'name' => 'required|string|max:255',
            'email' => 'string|email|max:255|unique:users',
            'country_code' => 'required|string',

        ]);

        if($validator->fails()) {
            return response(['message' => 'Validation errors', 'errors' =>  $validator->errors(), 'status' => false], 422);
        }

        $input = $request->all();
        $generatedPassword = mt_rand(1000, 9999);
        $input['password'] = Hash::make($generatedPassword);
        $user = User::create($input);
        $this->generateActivationCode($user, $notificationService);

        return response(['message' => 'success', 'errors' => null, 'status' => true, 'data' => ['message' => 'You will receive an activation code']], 200);

        //$client = Client::where('password_client', 1)->first();

        //send text message to user

//        // Fire off the internal request.
//        $token = Request::create(
//            'api/v1/login',
//            'POST',
//            [
//                'grant_type'    => 'password',
//                'client_id'     => $client->id,
//                'client_secret' => $client->secret,
//                'username'      => $input['email'],
//                'password'      => $input['password'],
//                'scope'         => '*',
//            ]
//        );
//        return App::dispatch($token);
    }

    /**
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function logout() {
        $user = Auth::user();
        if ($user) {
            $user->token()->revoke();
            return response(['message' => 'success', 'errors' => null, 'status' => true, 'data' => []], 200);
        }
        return response(['message' => 'failed', 'errors' => 'not logged in', 'status' => false, 'data' => []], 403);
    }

    /**
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function getUser() {
        $user = Auth::user();
        return response(['message' => 'success', 'errors' => null, 'status' => true, 'data' => $user], 200);
    }

    /**
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function getUserById(Request $request, int $id) {
        $user = User::find($id);
        if ($user) {
            return response(['message' => 'success', 'errors' => null, 'status' => true, 'data' => $user], 200);
        }
        return response(['message' => 'failed', 'errors' => 'content not found', 'status' => false, 'data' => []], 404);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function forgetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'identifier' => 'required'
        ]);

        if($validator->fails()) {
            return response(['message' => 'Validation errors', 'errors' =>  $validator->errors(), 'status' => false], 422);
        }
        $identifier = $request->get('identifier');
        $user = User::where('phone', $identifier)->orWhere('email', $identifier)->first();
        if ($user) {
            $token = make_random_hash($user->email);
            $user->setAttribute('reset_password_token', $token)
                ->save();
            //TODO: Notify user
            return response([
                'message' => 'success',
                'errors' => null,
                'status' => true,
                'data' => ['message' => 'Check your email to reset the password.']
            ], 200);
        }
        return response(['message' => 'failed', 'errors' => 'content not found', 'status' => false, 'data' => []], 404);
    }

    /**
     * @param Request $request
     * @param string $token
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function resetPassword(Request $request, string $token)
    {
        $params = $request->all();
        $params['token'] = $token;
        $validator = Validator::make($params, [
            'token' => 'required|string',
            'password' => 'required|string|min:6'
        ]);

        if($validator->fails()) {
            return response(['message' => 'Validation errors', 'errors' =>  $validator->errors(), 'status' => false], 422);
        }

        $user = User::where('reset_password_token', $token)->first();
        if ($user) {
            $password = Hash::make($params['password']);
            $user->setAttribute('password', $password)
                ->setAttribute('reset_password_token', null)
                ->save();
            $userToken = $user->token();
            if ($userToken) {
                $userToken->revoke();
            }
            return response([
                'message' => 'success',
                'errors' => null,
                'status' => true,
                'data' => ['message' => 'Password was reset successfully. You need to login again.']
            ], 200);
        }
        return response(['message' => 'failed', 'errors' => 'content not found', 'status' => false, 'data' => []], 404);
    }

    /**
     * @param Request $request
     * @param string $token
     * @return \Illuminate\View\View
     */
    public function resetPasswordForm(Request $request, string $token)
    {
        return view('reset-password', ['token' => $token]);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function changePassword(Request $request)
    {
        $params = $request->all();
        $validator = Validator::make($params, [
            'old_password' => 'required|string',
            'new_password' => 'required|string|min:6'
        ]);

        if($validator->fails()) {
            return response(['message' => 'Validation errors', 'errors' =>  $validator->errors(), 'status' => false], 422);
        }

        $user = Auth::user();
        if ($user && Hash::check($params['old_password'], $user->getAttribute('password'))) {
            $user->setAttribute('password', Hash::make($params['new_password']))->save();
            return response([
                'message' => 'success',
                'errors' => null,
                'status' => true,
                'data' => ['message' => 'Password was changed successfully.']
            ], 200);
        }
        return response(['message' => 'failed', 'errors' => 'content not found', 'status' => false, 'data' => []], 404);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function authenticate(Request $request)
    {
        $user = Auth::user();
        if ($user) {
            return response([
                'message' => 'success',
                'errors' => null,
                'status' => true,
                'data' => $user->toArray()
            ], 200);
        }
        return response(['message' => 'failed', 'errors' => 'content not found', 'status' => false, 'data' => []], 404);
    }

    /**
     * @param Request $request
     * @param NotificationService $notificationService
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function codeRequest(Request $request, NotificationService $notificationService)
    {
        $validator = Validator::make($request->all(), [
            'identifier' => 'required'
        ]);

        if($validator->fails()) {
            return response(['message' => 'Validation errors', 'errors' =>  $validator->errors(), 'status' => false], 422);
        }
        $identifier = $request->get('identifier');
        $user = User::where('phone', $identifier)->orWhere('email', $identifier)->first();
        if ($user) {
            $this->generateActivationCode($user, $notificationService);
            return response(['message' => 'success', 'errors' => null, 'status' => true, 'data' => ['message' => 'You will receive an activation code']], 200);
        }
        return response(['message' => 'failed', 'errors' => 'content not found', 'status' => false, 'data' => []], 404);
    }

    /**
     * @param User $user
     * @param NotificationService $notificationService
     * @param string $password
     */
    private function generateActivationCode(User $user, NotificationService $notificationService, string $password = '')
    {
        if (!$password) {
            $password = str_pad(mt_rand(1000, 9999), 4, "0");
            $user->setAttribute('password', Hash::make($password))->save();
        }
        $notificationService->sendLoginActivationCode($user, $password);
    }
}
