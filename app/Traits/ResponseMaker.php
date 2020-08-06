<?php

namespace App\Traits;


trait ResponseMaker
{
    /**
     * @param $data
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    protected function success($data)
    {
        return response(
            [
                'message' => 'success',
                'errors' => null, 'status' => true,
                'data' => $data
            ], 200
        );
    }

    /**
     * @param string $message
     * @param int $status
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    protected function failMessage(string $message, int $status)
    {
        return response(
            ['message' => 'failed',
                'errors' => ['message' => $message],
                'status' => false
            ], $status
        );
    }

    /**
     * @param $data
     * @param int $status
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    protected function failData($data, int $status)
    {
        return response(
            ['message' => 'failed',
                'errors' => $data,
                'status' => false
            ], $status
        );
    }

    /**
     * @param $errors
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    protected function failValidation($errors)
    {
        return response(
            [
                'message' => 'Validation errors',
                'errors' => $errors,
                'status' => false
            ], 422
        );
    }
}