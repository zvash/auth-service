<?php

namespace App;

use App\Exceptions\ServiceException;
use Illuminate\Database\Eloquent\Model;

class Config extends Model
{
    protected $fillable = ['key', 'value'];

    /**
     * @param string $key
     * @return mixed
     * @throws ServiceException
     */
    public static function getValue(string $key)
    {
        $config = Config::where('key', $key)->first();
        if ($config) {
            return $config->value;
        }
        throw new ServiceException('Config not found for key: ' . $key, [
            'message' => 'Config not found for key: ' . $key
        ]);
    }
}
