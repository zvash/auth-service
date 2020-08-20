<?php

namespace App\Traits;


use Illuminate\Support\MessageBag;

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
            [
                'message' => 'failed',
                'errors' => ['message' => $message],
                'status' => false,
                'data' => []
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
            [
                'message' => 'failed',
                'errors' => $data,
                'status' => false,
                'data' => []
            ], $status
        );
    }

    /**
     * @param $errors
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    protected function failValidation($errors)
    {
        if ($errors instanceof MessageBag) {
            $errors = $errors->toArray();
        }
        $errors['message'] = 'Validation error';
        return response(
            [
                'message' => 'failed',
                'errors' => $errors,
                'status' => false,
                'data' => []
            ], 422
        );
    }
}