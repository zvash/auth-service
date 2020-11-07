<?php

namespace App\Events;

class ProfileWasUpdated extends Event
{
    /**
     * @var int $userId
     */
    protected $userId;

    /**
     * Create a new event instance.
     *
     * @param int $userId
     */
    public function __construct(int $userId)
    {
        $this->userId = $userId;
    }

    /**
     * @return int
     */
    public function getUserId()
    {
        return $this->userId;
    }
}
