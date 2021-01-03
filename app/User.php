<?php

namespace App;

use Illuminate\Auth\Authenticatable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;
use Laravel\Lumen\Auth\Authorizable;
use Laravel\Passport\HasApiTokens;

/**
 * @property mixed referral_code
 * @property mixed image
 * @property int referred_by
 * @property int id
 * @property string phone
 * @property boolean completed_profile
 * @property mixed email
 * @property mixed name
 * @property mixed gender
 * @property mixed date_of_birth
 * @property string iban
 */
class User extends Model implements AuthenticatableContract, AuthorizableContract
{
    use HasApiTokens, Authenticatable, Authorizable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'phone', 'password', 'country', 'currency', 'date_of_birth', 'gender', 'referral_code'
    ];

    /**
     * @var array
     */
    protected $appends = ['image_url', 'masked_phone', 'completion_percent'];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'reset_password_token',
        'referred_by',
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
     * @return string
     */
    public function setReferralCode()
    {
        if ($this->referral_code) {
            return $this->referral_code;
        }
        $referralCode = make_random_referral_code();
        try {
            $this
                ->setAttribute('referral_code', $referralCode)
                ->save();
            return $referralCode;
        } catch (\Illuminate\Database\QueryException $exception) {
            $message = $exception->getMessage();
            if (strpos($message, 'Duplicate entry') !== false) {
                return $this->setReferralCode();
            }
        }
    }

    /**
     * @param string $role
     * @return bool
     */
    public function hasRole(string $role)
    {
        return in_array($role, $this->roles->pluck('name')->toArray());
    }

    /**
     * @return string
     */
    public function getImageUrlAttribute()
    {
        if ($this->image) {
            $prefix = rtrim(env('APP_URL'), '/') . '/';
            return $prefix . $this->image;
        }
        return $this->image;
    }

    /**
     * @return mixed|null
     */
    public function getMaskedPhoneAttribute()
    {
        if ($this->phone) {
            return substr_replace($this->phone, '.....', -9, 6);
        }
        return null;
    }

    /**
     * @return bool
     */
    public function isNewUser()
    {
        if ($this->hasRole('admin')) {
            return false;
        }
        return $this->last_logged_in == null;
    }

    /**
     * @return float|int
     */
    public function getCompletionPercentAttribute()
    {
        $data = [
            'has_mail' => !!$this->email,
            'has_image' => !!$this->image,
            'has_name' => !!$this->name,
            'has_gender' => !!$this->gender,
            'has_date_of_birth' => !!$this->date_of_birth
        ];

        return (array_sum(array_values($data)) * 20);
    }
}
