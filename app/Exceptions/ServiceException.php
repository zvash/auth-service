<?php

namespace App\Exceptions;


class ServiceException extends \Exception
{
    /**
     * @var string $message
     */
    protected $message;

    /**
     * @var array $data
     */
    protected $data;

    /**
     * ServiceException constructor.
     * @param string $message
     * @param array $data
     */
    public function __construct(string $message, array $data = [])
    {
        $this->message = $message;
        $this->data['data'] = $data;
    }

    /**
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @return string
     */
    public function getCustomMessage()
    {
        return $this->message;
    }

}