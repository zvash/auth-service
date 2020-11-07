<?php

namespace App\Http\Controllers\Api\V1;

use App\Config;
use App\Exceptions\ServiceException;
use App\Traits\ResponseMaker;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class ConfigController extends Controller
{
    use ResponseMaker;

    /**
     * @param Request $request
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function set(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'key' => 'required|unique:configs,key',
            'value' => 'required|string'
        ]);

        if ($validator->fails()) {
            return $this->failValidation($validator->errors());
        }

        $data = [
            'key' => $request->get('key'),
            'value' => $request->get('value')
        ];
        $config = Config::create($data);
        return $this->success($config);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function all(Request $request)
    {
        $configs = Config::all();
        return $this->success($configs);
    }

    /**
     * @param Request $request
     * @param string $key
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function update(Request $request, string $key)
    {
        $data = $request->all();
        $data['key'] = $key;
        $validator = Validator::make($data, [
            'key' => 'required|exists:configs,key',
            'value' => 'required|string'
        ]);

        if ($validator->fails()) {
            return $this->failValidation($validator->errors());
        }

        $config = Config::where('key', $key)->first();
        $config->value = $data['value'];
        $config->save();
        return $this->success($config);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function getReferralCoinAmount(Request $request)
    {
        try {
            $amount = Config::getValue('refer_coins') * 1;
        } catch (ServiceException $e) {
            $amount = 0;
        }
        return $this->success(['refer_coins' => $amount]);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function get(Request $request)
    {
        try {
            $referAmount = Config::getValue('refer_coins') * 1;
            $profileAmount = Config::getValue('profile_completion_coins') * 1;
        } catch (ServiceException $e) {
            $referAmount = 0;
            $profileAmount = 0;
        }
        return $this->success(['refer_coins' => $referAmount, 'profile_completion_coins' => $profileAmount]);
    }
}
