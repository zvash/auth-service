<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use App\Traits\ResponseMaker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PlayerController extends Controller
{
    use ResponseMaker;

    /**
     * @param Request $request
     * @param NotificationService $notificationService
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function registerPlayerId(Request $request, NotificationService $notificationService)
    {
        $validator = Validator::make($request->all(), [
            'player_id' => 'required|string',
            'platform' => 'required|string|in:web,android,ios',
            'device_token' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return $this->failValidation($validator->errors());
        }
        $user = Auth::user();
        if ($user) {
            $playerId = $request->get('player_id');
            $platform = $request->get('platform');
            $deviceToken = $request->exists('device_token') ? $request->get('device_token') : null;
            $response = $notificationService->registerPlayer($user->id, $playerId, $platform, $deviceToken);
            if ($response['status'] == 200) {
                return $this->success(['player_token' => $response['data']['player_token']]);
            } else {
                return $this->failMessage($response['data'], 400);
            }
        }
        return $this->failMessage('Content not found.', 404);
    }
}
