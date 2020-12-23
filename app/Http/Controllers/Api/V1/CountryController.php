<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class CountryController extends Controller
{
    /**
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function getAll()
    {
        return response(
            [
                'message' => 'success',
                'errors' => null,
                'status' => true,
                'data' => array_values(config('active_countries', []))
            ], 200
        );
    }

}