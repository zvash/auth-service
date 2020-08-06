<?php

function make_random_hash(string $salt = '')
{
    try {
        $string = bin2hex(random_bytes(32)) . $salt;
    } catch (Exception $e) {
        $string = mt_rand() . $salt;
    }
    return sha1($string);
}

function make_random_referral_code()
{
    return strtoupper(substr(make_random_hash(),4,5));
}

