<?php

namespace App;

use Illuminate\Auth\Authenticatable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;
use Laravel\Lumen\Auth\Authorizable;
use Laravel\Passport\HasApiTokens;

class User extends Model implements AuthenticatableContract, AuthorizableContract
{
    use HasApiTokens, Authenticatable, Authorizable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'phone', 'password', 'country', 'date_of_birth', 'gender', 'referral_code'
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'reset_password_token',
    ];

    /**
     * The roles that belong to the user.
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_user');
    }

    /**
     * Override the field which is used for username in the authentication
     * @param string $username
     * @return User
     */
    public function findForPassport(string $username)
    {
        return $this->where('email', $username)->orWhere('phone', $username)->first();
    }

    /**
     * @return User
     */
    public function forceResetPassword()
    {
        $password = Hash::make(make_random_hash());
        $this->setAttribute('password', $password)->save();
        return $this;
    }

    /**
     * Set Referral Code for User.
     */
    public function setReferralCode()
    {
        $referralCode = make_random_referral_code();
        try {
            $this
                ->setAttribute('referral_code', $referralCode)
                ->save();
        } catch (\Illuminate\Database\QueryException $exception) {
            $message = $exception->getMessage();
            if (strpos($message, 'Duplicate entry') !== false) {
                $this->setReferralCode();
            }
        }
    }
}
