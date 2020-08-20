<?php

namespace App\Http\Controllers\Api\V1;

use App\Traits\ResponseMaker;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\App;
use Laravel\Passport\Client;

class AccessTokenController extends Controller
{
    use ResponseMaker;

    /**
     * Authorize a client to access the user's account.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function issueToken(Request $request)
    {
        $client = Client::where('password_client', 1)->first();

        $inputs = $request->all();

        //        // Fire off the internal request.
        $token = Request::create(
            'api/v1/login-passport',
            'POST',
            [
                'grant_type' => 'password',
                'client_id' => $client->id,
                'client_secret' => $client->secret,
                'username' => $inputs['username'],
                'password' => $inputs['password'],
                'scope' => '*',
            ]
        );

        $loginResponse = App::dispatch($token);
        $statusCode = $loginResponse->getStatusCode();
        $content = json_decode($loginResponse->getContent(), 1);
        if ($statusCode == 200) {
            return $this->success($content);
        } else {
            $data = [];
            $data['error'] = $content['error'];
            $data['message'] = 'Authentication failed.';
            return $this->failData($data, $statusCode);
        }
    }
}
