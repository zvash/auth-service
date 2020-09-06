<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $fillable = ['name', 'token'];

    /**
     * @param string $name
     * @return Service
     */
    public static function register(string $name)
    {
        $token = make_random_hash($name);
        $service = Service::where('token', $token)->first();
        if (!$service) {
            $service = Service::create(['name' => $name, 'token' => $token]);
            return $service;
        } else {
            return static::register($name);
        }
    }
}
