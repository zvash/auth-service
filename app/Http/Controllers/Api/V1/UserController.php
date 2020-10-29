<?php

namespace App\Http\Controllers\Api\V1;

use App\Config;
use App\Events\UserHasCompletedATaskForTheFirstTime;
use App\Exceptions\ReferSelfException;
use App\Exceptions\ServiceException;
use App\Repositories\UserRepository;
use App\Role;
use App\Services\BillingService;
use App\User;
use Laravel\Passport\Client;
use Illuminate\Http\Request;
use App\Traits\ResponseMaker;
use App\Utils\CountryRepository;
use Illuminate\Support\Facades\App;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{

    use ResponseMaker;

    /**
     * @param Request $request
     * @param NotificationService $notificationService
     * @param CountryRepository $countryRepository
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function register(Request $request, NotificationService $notificationService, CountryRepository $countryRepository)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|regex:/^[1-9]{1}[0-9]{9}$/',
            'country' => 'required|string|in:' . $countryRepository->getAllNameVariationsAsString()
        ]);

        if ($validator->fails()) {
            return $this->failValidation($validator->errors());
        }
        $input = $request->only(['phone', 'country']);
        $countryName = $countryRepository->getName($input['country']);
        $countryCode = $countryRepository->getPhonePrefix($input['country']);
        $currency = $countryRepository->getCurrency($input['country']);
        $generatedPassword = mt_rand(1000, 9999);
        $password = Hash::make($generatedPassword);
        $input['phone'] = $countryCode . $input['phone'];
        $user = User::firstOrCreate(
            ['phone' => $input['phone']],
            ['country' => $countryName, 'currency' => $currency, 'password' => $password]
        );

        $user->setReferralCode();

        if (!$user->hasRole('admin')) {
            if (!$user->hasRole('normal')) {
                $role = Role::where('name', 'normal')->first();
                $user->roles()->attach($role->id);
            }
            $password = $this->generateActivationCode($user, $notificationService);
            return $this->success(['username' => $user->phone, 'password' => $password]);
        }
        return $this->success(['message' => 'You cannot log in through this url']);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function update(Request $request)
    {
        $user = Auth::user();
        if ($user) {
            $currentTime = \Carbon\Carbon::now()->format('Y-m-d');
            $validator = Validator::make($request->all(), [
                'name' => 'string',
                'email' => 'string|email|max:255|unique:users,email,' . $user->id,
                'gender' => 'string|in:male,female,custom',
                'date_of_birth' => 'date_format:Y-m-d|before:' . $currentTime,
                'image' => 'mimes:jpeg,jpg,png',
                'referral_code' => 'string|exists:users,referral_code'
            ]);

            if ($validator->fails()) {
                return $this->failValidation($validator->errors());
            }

            try {
                $this->saveReferredBy($request, $user);
            } catch (ServiceException $exception) {
                return $this->failData($exception->getData(), 400);
            }

            $path = $this->saveProfileImage($request);
            $inputs = array_filter($request->all(), function ($key) {
                return in_array($key, ['name', 'email', 'gender', 'date_of_birth']);
            }, ARRAY_FILTER_USE_KEY);
            if ($path) {
                $inputs['image'] = $path;
            }
            foreach ($inputs as $key => $value) {
                $user->setAttribute($key, $value);
            }
            $user->save();

            return $this->success($user);
        }

        return $this->failMessage('Content not fount.', 404);
    }

    /**
     * @param Request $request
     * @param CountryRepository $countryRepository
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function registerAdmin(Request $request, CountryRepository $countryRepository)
    {
        $currentTime = \Carbon\Carbon::now()->format('Y-m-d');
        $validator = Validator::make($request->all(), [
            'phone' => 'required|regex:/^[1-9]{1}[0-9]{9}$/',
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string',
            'country' => 'required|string|in:' . $countryRepository->getAllNameVariationsAsString(),
            'gender' => 'string|in:male,female,custom',
            'date_of_birth' => 'date_format:Y-m-d|before:' . $currentTime
        ]);

        if ($validator->fails()) {
            return $this->failValidation($validator->errors());
        }

        $input = $request->all();
        $providedPassword = $input['password'];
        $input['password'] = Hash::make($input['password']);
        $input['phone'] = $countryRepository->getPhonePrefix($input['country']) . $input['phone'];
        $input['currency'] = $countryRepository->getCurrency($input['country']);
        $input['country'] = $countryRepository->getName($input['country']);
        $data = array_filter($input, function ($key) {
            return in_array($key, ['phone', 'name', 'email', 'password', 'country', 'gender', 'date_of_birth', 'currency']);
        }, ARRAY_FILTER_USE_KEY);
        $user = User::create($data);
        $user->setReferralCode();
        $roleIds = Role::whereIn('name', ['normal', 'admin'])->get()->pluck('id')->toArray();
        $user->roles()->attach($roleIds);

        $client = Client::where('password_client', 1)->first();

        $token = Request::create(
            'api/v1/login',
            'POST',
            [
                'grant_type' => 'password',
                'client_id' => $client->id,
                'client_secret' => $client->secret,
                'username' => $input['phone'],
                'password' => $providedPassword,
                'scope' => '*',
            ]
        );
        return App::dispatch($token);
    }

    /**
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function logout()
    {
        $user = Auth::user();
        if ($user) {
            $user->token()->revoke();
            return $this->success([]);
        }
        return $this->failMessage('Not logged in.', 400);
    }

    /**
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function getUser()
    {
        $user = Auth::user();
        return $this->success($user);
    }

    /**
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function getUserById(Request $request, int $id)
    {
        $user = User::find($id);
        if ($user) {
            return $this->success($user);
        }
        return $this->failMessage('Content not found.', 404);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function forgetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'identifier' => 'required'
        ]);

        if ($validator->fails()) {
            return $this->failValidation($validator->errors());
        }
        $identifier = $request->get('identifier');
        $user = User::where('phone', $identifier)->orWhere('email', $identifier)->first();
        if ($user && $user->hasRole('admin')) {
            $token = make_random_hash($user->email);
            $user->setAttribute('reset_password_token', $token)
                ->save();
            //TODO: Notify user

            return $this->success(['message' => "Check your email to reset the password.(url:" . rtrim(env('APP_URL'), '/') . "/password/forget/$token)"]);
        }
        return $this->failMessage('Content not found.', 404);
    }

    /**
     * @param Request $request
     * @param string $token
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function resetPassword(Request $request, string $token)
    {
        $params = $request->all();
        $params['token'] = $token;
        $validator = Validator::make($params, [
            'token' => 'required|string',
            'password' => 'required|string|min:6'
        ]);

        if ($validator->fails()) {
            return $this->failValidation($validator->errors());
        }

        $user = User::where('reset_password_token', $token)->first();
        if ($user && $user->hasRole('admin')) {
            $password = Hash::make($params['password']);
            $user->setAttribute('password', $password)
                ->setAttribute('reset_password_token', null)
                ->save();
            $userToken = $user->token();
            if ($userToken) {
                $userToken->revoke();
            }
            return $this->success(['message' => 'Password was reset successfully. You need to login again.']);
        }
        return $this->failMessage('Content not found.', 404);
    }

    /**
     * @param Request $request
     * @param string $token
     * @return \Illuminate\View\View
     */
    public function resetPasswordForm(Request $request, string $token)
    {
        return view('reset-password', ['token' => $token]);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function changePassword(Request $request)
    {
        $params = $request->all();
        $validator = Validator::make($params, [
            'old_password' => 'required|string',
            'new_password' => 'required|string|min:6'
        ]);

        if ($validator->fails()) {
            return $this->failValidation($validator->errors());
        }

        $user = Auth::user();
        if ($user && Hash::check($params['old_password'], $user->getAttribute('password'))) {
            $user->setAttribute('password', Hash::make($params['new_password']))->save();
            return $this->success(['message' => 'Password was changed successfully.']);
        }
        return $this->failMessage('Content not found.', 404);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function authenticate(Request $request)
    {
        $user = Auth::user();
        $publicFields = ['id', 'name', 'phone', 'email', 'country', 'currency', 'date_of_birth', 'gender', 'referral_code', 'image_url', 'roles'];
        if ($user) {
            $roles = $user->roles->pluck('name')->toArray();
            $userArray = array_filter($user->toArray(),
                function ($key) use ($publicFields) {
                    return in_array($key, $publicFields);
                },
                ARRAY_FILTER_USE_KEY);
            $userArray['roles'] = $roles;
            return $this->success($userArray);
        }
        return $this->failMessage('Content not found.', 404);
    }

    /**
     * @param Request $request
     * @param NotificationService $notificationService
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function codeRequest(Request $request, NotificationService $notificationService)
    {
        $validator = Validator::make($request->all(), [
            'identifier' => 'required'
        ]);

        if ($validator->fails()) {
            return response(['message' => 'Validation errors', 'errors' => $validator->errors(), 'status' => false], 422);
        }
        $identifier = $request->get('identifier');
        $user = User::where('phone', $identifier)->orWhere('email', $identifier)->first();
        if ($user) {
            $this->generateActivationCode($user, $notificationService);
            return response(['message' => 'success', 'errors' => null, 'status' => true, 'data' => ['message' => 'You will receive an activation code']], 200);
        }
        return response(['message' => 'failed', 'errors' => ['message' => 'content not found'], 'status' => false, 'data' => []], 404);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function deleteProfileImage(Request $request)
    {
        $user = Auth::user();
        if ($user) {
            $user->setAttribute('image', null)->save();
            return $this->success($user);
        }
        return $this->failMessage('Content not found', 404);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function profileStatus(Request $request)
    {
        $user = Auth::user();
        if ($user) {
            $data = [
                'has_mail' => !!$user->email,
                'has_image' => !!$user->image,
                'has_name' => !!$user->name,
                'has_gender' => !!$user->gender,
                'has_date_of_birth' => !!$user->date_of_birth
            ];

            $data['completion_percent'] = (array_sum(array_values($data)) * 20);
            $data['completion_status'] = $data['completion_percent'] . '%';
            return $this->success($data);

        }
        return $this->failMessage('Content not found.', 404);
    }

    /**
     * @param Request $request
     * @param int $userId
     * @param BillingService $billingService
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function completeTask(Request $request, int $userId, BillingService $billingService)
    {
        $user = User::find($userId);
        if ($user) {
            if (!$user->completed_a_task) {
                $user->completed_a_task = true;
                $user->save();
                event(new UserHasCompletedATaskForTheFirstTime($user, $billingService));
            }

            return $this->success($user);
        }
        return $this->failMessage('Content not found.', 404);
    }

    /**
     * @param Request $request
     * @param UserRepository $userRepository
     * @param BillingService $billingService
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function referralSummary(Request $request, UserRepository $userRepository, BillingService $billingService)
    {
        $user = Auth::user();
        if ($user) {
            $referringUsersPaginatedArray = User::where('referred_by', $user->id)
                ->orderBy('id', 'DESC')
                ->paginate(10, ['id', 'phone', 'name', 'image'])
                ->toArray();

            if ($referringUsersPaginatedArray) {
                $referringUsers = $referringUsersPaginatedArray['data'];
                try {
                    $referPrizes = $userRepository->getCoinsFromReferrals($referringUsers, $billingService);
                    foreach ($referringUsers as $index => $referringUser) {
                        if (isset($referPrizes['users'][$referringUser['id']]) && $referPrizes['users'][$referringUser['id']]['COIN']) {
                            $referringUsers[$index]['refer_prize'] = [
                                'currency' => 'COIN',
                                'amount' => $referPrizes['users'][$referringUser['id']]['COIN']
                            ];
                            $referringUsers[$index]['status'] = 'received';
                        } else {
                            $referringUsers[$index]['refer_prize'] = [
                                'currency' => 'COIN',
                                'amount' => 0
                            ];
                            $referringUsers[$index]['status'] = 'pending';
                        }
                        unset($referringUsers[$index]['id']);
                        unset($referringUsers[$index]['phone']);
                        unset($referringUsers[$index]['image']);
                    }
                    $referringUsersPaginatedArray['data'] = $referringUsers;
                    return $this->success($referringUsersPaginatedArray);
                } catch (ServiceException $exception) {
                    return $this->failData($exception->getData(), 400);
                }
            }
            return $this->failMessage('Content not found.', 404);
        }
        return $this->failMessage('Content not found.', 404);
    }

    /**
     * @param Request $request
     * @param UserRepository $userRepository
     * @param BillingService $billingService
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function referralStatistics(Request $request, UserRepository $userRepository, BillingService $billingService)
    {
        $user = Auth::user();
        if ($user) {
            try {
                $pendingAmount = Config::getValue('refer_coins');
            } catch (ServiceException $e) {
                $pendingAmount = 0;
            }
            $referringUsers = User::where('referred_by', $user->id)
                ->orderBy('id', 'DESC')
                ->get()
                ->toArray();

            if ($referringUsers) {
                try {
                    $referPrizes = $userRepository->getCoinsFromReferrals($referringUsers, $billingService);
                    $totalReceived = 0;
                    $totalPending = 0;
                    foreach ($referringUsers as $index => $referringUser) {
                        if (isset($referPrizes['users'][$referringUser['id']]) && $referPrizes['users'][$referringUser['id']]['COIN']) {
                            $totalReceived += $referPrizes['users'][$referringUser['id']]['COIN'];
                        } else {
                            $totalPending += $pendingAmount;
                        }
                    }
                    return $this->success([
                        'received' => $totalReceived,
                        'pending' => $totalPending,
                        'currency' => 'COIN',
                    ]);
                } catch (ServiceException $exception) {
                    return $this->failData($exception->getData(), 400);
                }
            }
            return $this->success([
                'received' => 0,
                'pending' => 0,
                'currency' => 'COIN',
            ]);
        }
        return $this->failMessage('Content not found.', 404);
    }

    /**
     * @param User $user
     * @param NotificationService $notificationService
     * @param string $password
     * @return string
     */
    private function generateActivationCode(User $user, NotificationService $notificationService, string $password = '')
    {
        if (!$password) {
            $password = str_pad(mt_rand(1000, 9999), 4, "0");
            $user->setAttribute('password', Hash::make($password))->save();
        }
        $notificationService->sendLoginActivationCode($user, $password);
        return $password;
    }

    /**
     * @param Request $request
     * @return null|string|string[]
     */
    private function saveProfileImage(Request $request)
    {
        $path = null;
        if ($request->hasFile('image')) {
            $publicImagesPath = rtrim(env('PUBLIC_IMAGES_PATH', 'public/images'), '/');
            $file = $request->file('image');
            $path = preg_replace(
                '#public/#',
                'storage/',
                Storage::putFile($publicImagesPath, $file)
            );
        }
        return $path;
    }

    /**
     * @param Request $request
     * @param User $user
     * @throws ReferSelfException
     * @throws ServiceException
     */
    private function saveReferredBy(Request $request, User $user)
    {
        if ($request->has('referral_code')) {
            $referralCode = $request->get('referral_code');
            if ($referralCode == $user->referral_code) {
                throw new ReferSelfException('Users cannot refer themselves.', [
                    'message' => 'Users cannot refer themselves.',
                    'user' => $user,
                    'referral_code' => $referralCode
                ]);
            }
            if ($user->referred_by) {
                throw new ReferSelfException('You have already set another referral code.', [
                    'message' => 'You have already set another referral code.',
                    'user' => $user,
                    'referral_code' => $referralCode
                ]);
            }
            $referredByUser = User::where('referral_code', $referralCode)->first();
            if (!$referredByUser) {
                throw new ServiceException('Referral code does not belong to any user.', [
                    'message' => 'Referral code does not belong to any user.',
                    'user' => null,
                    'referral_code' => $referralCode
                ]);
            }
            $user->referred_by = $referredByUser->id;
            $user->save();
        }
    }
}
