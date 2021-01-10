<?php

namespace App\Http\Controllers\Api\V1;

use App\Config;
use App\Events\ProfileWasUpdated;
use App\Events\UserHasCompletedATaskForTheFirstTime;
use App\Exceptions\ReferSelfException;
use App\Exceptions\ServiceException;
use App\Repositories\UserRepository;
use App\Role;
use App\Services\AffiliateService;
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
            'phone' => 'required|regex:/^[1-9]{1}[0-9]{7,9}$/',
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
     * @param BillingService $billingService
     * @param AffiliateService $affiliateService
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function update(Request $request, BillingService $billingService, AffiliateService $affiliateService)
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
                $this->saveReferredBy($request, $user, $affiliateService);
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

            try {
                $user->profile_completion_coins = $this->justCompletedTheProfile($user);
            } catch (ServiceException $e) {
                $user->profile_completion_coins = 0;
            }

            event(new ProfileWasUpdated($user->id, $billingService));

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
            'phone' => 'required|regex:/^[1-9]{1}[0-9]{7,9}$/',
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
     * @param Request $request
     * @param NotificationService $notificationService
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function logout(Request $request, NotificationService $notificationService)
    {
        $user = Auth::user();
        if ($user) {
            $playerId = $request->exists('player_id') ? $request->get('player_id') : null;
            $playerToken = $request->exists('player_token') ? $request->get('player_token') : null;
            $deviceToken = $request->exists('device_token') ? $request->get('device_token') : null;
            $platform = $request->exists('platform') ? $request->get('platform') : null;
            $notificationService->removePlayer($user->id, $playerId, $platform, $deviceToken, $playerToken);

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
     * @param int $userId
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function getUserById(Request $request, int $userId)
    {
        $user = User::find($userId);
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
     * @param UserRepository $userRepository
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function getIBan(Request $request, UserRepository $userRepository)
    {
        $user = Auth::user();
        if ($user) {
            $iban = $userRepository->getIBan($user);
            return $this->success(['iban' => $iban]);
        }
        return $this->failMessage('Content not found.', 404);
    }

    /**
     * @param Request $request
     * @param UserRepository $userRepository
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function setIBan(Request $request, UserRepository $userRepository)
    {
        Validator::extend('iban', function ($attribute, $value, $parameters) {
            $iban = strtolower(str_replace(' ','', $value));
            $countries = array('al'=>28,'ad'=>24,'at'=>20,'az'=>28,'bh'=>22,'be'=>16,'ba'=>20,'br'=>29,'bg'=>22,'cr'=>21,'hr'=>21,'cy'=>28,'cz'=>24,'dk'=>18,'do'=>28,'ee'=>20,'fo'=>18,'fi'=>18,'fr'=>27,'ge'=>22,'de'=>22,'gi'=>23,'gr'=>27,'gl'=>18,'gt'=>28,'hu'=>28,'is'=>26,'ie'=>22,'il'=>23,'it'=>27,'jo'=>30,'kz'=>20,'kw'=>30,'lv'=>21,'lb'=>28,'li'=>21,'lt'=>20,'lu'=>20,'mk'=>19,'mt'=>31,'mr'=>27,'mu'=>30,'mc'=>27,'md'=>24,'me'=>22,'nl'=>18,'no'=>15,'pk'=>24,'ps'=>29,'pl'=>28,'pt'=>25,'qa'=>29,'ro'=>24,'sm'=>27,'sa'=>24,'rs'=>22,'sk'=>24,'si'=>19,'es'=>24,'se'=>24,'ch'=>21,'tn'=>24,'tr'=>26,'ae'=>23,'gb'=>22,'vg'=>24);
            $chars = array('a'=>10,'b'=>11,'c'=>12,'d'=>13,'e'=>14,'f'=>15,'g'=>16,'h'=>17,'i'=>18,'j'=>19,'k'=>20,'l'=>21,'m'=>22,'n'=>23,'o'=>24,'p'=>25,'q'=>26,'r'=>27,'s'=>28,'t'=>29,'u'=>30,'v'=>31,'w'=>32,'x'=>33,'y'=>34,'z'=>35);

            if (!isset($countries[substr($iban,0,2)])) {
                return false;
            }

            if(strlen($iban) == $countries[substr($iban,0,2)]){

                $movedChar = substr($iban, 4).substr($iban,0,4);
                $movedCharArray = str_split($movedChar);
                $newString = "";

                foreach($movedCharArray AS $key => $value){
                    if(!is_numeric($movedCharArray[$key])){
                        $movedCharArray[$key] = $chars[$movedCharArray[$key]];
                    }
                    $newString .= $movedCharArray[$key];
                }

                if(bcmod($newString, '97') == 1)
                {
                    return true;
                }
            }
            return false;
        });
        $validator = Validator::make($request->all(), [
            'iban' => 'nullable|iban',
        ], [
            'iban.iban' => 'Invalid IBAN'
        ]);

        if ($validator->fails()) {
            return $this->failValidation($validator->errors());
        }

        $user = Auth::user();
        if ($user) {
            $iban = $request->exists('iban') ? $request->get('iban') : null;
            $user = $userRepository->setIBan($user, $iban);
            return $this->success($user);
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
        $publicFields = ['id', 'name', 'phone', 'email', 'country', 'currency', 'date_of_birth', 'gender', 'referral_code', 'image_url', 'roles', 'completion_percent', 'masked_phone'];
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
     * @param AffiliateService $affiliateService
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function completeTask(Request $request, int $userId, BillingService $billingService, AffiliateService $affiliateService)
    {
        $user = User::find($userId);
        if ($user) {
            if (!$user->completed_a_task) {
                $user->completed_a_task = true;
                $user->save();
                event(new UserHasCompletedATaskForTheFirstTime($user, $billingService, $affiliateService));
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
     * @param Request $request
     * @param NotificationService $notificationService
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function notifications(Request $request, NotificationService $notificationService)
    {
        $user = Auth::user();
        if ($user) {
            $page = $request->exists('page') ? $request->get('page') : 1;
            $userId = $user->id;
            $response = $notificationService->getNotifications($userId, $page);
            if ($response['status'] == 200) {
                $notifications = $response['data'];
                $notifications['first_page_url'] = $this->replacePaginationUrlForNotifications($notifications['first_page_url']);
                $notifications['last_page_url'] = $this->replacePaginationUrlForNotifications($notifications['last_page_url']);
                $notifications['next_page_url'] = $this->replacePaginationUrlForNotifications($notifications['next_page_url']);
                $notifications['prev_page_url'] = $this->replacePaginationUrlForNotifications($notifications['prev_page_url']);
                $notifications['path'] = $this->replacePaginationUrlForNotifications($notifications['path']);
                return $this->success($notifications);
            }
            return $this->failMessage('Something went wrong in notification service', 400);
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
            if ($user->phone == '+971777777777') {
                $password = '7777';
            }
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
     * @param AffiliateService $affiliateService
     * @throws ReferSelfException
     * @throws ServiceException
     */
    private function saveReferredBy(Request $request, User $user, AffiliateService $affiliateService)
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
            $amount = Config::getValue('refer_coins') * 1;
            $affiliateService->registerClaim($user->referred_by, 'referrals', $user->id, $amount);
        }
    }

    /**
     * @param User $user
     * @return int
     * @throws ServiceException
     */
    private function justCompletedTheProfile(User $user)
    {
        $user->refresh();
        if ($user->completed_profile) {
            return 0;
        }
        $data = [
            'has_mail' => !!$user->email,
            'has_image' => !!$user->image,
            'has_name' => !!$user->name,
            'has_gender' => !!$user->gender,
            'has_date_of_birth' => !!$user->date_of_birth
        ];

        if ((array_sum(array_values($data)) * 20) == 100) {
            $amount = Config::getValue('profile_completion_coins');
            return $amount * 1;
        }
        return 0;
    }

    /**
     * @param null|string $currentUrl
     * @return null|string
     */
    private function replacePaginationUrlForNotifications(?string $currentUrl)
    {
        if (!$currentUrl) {
            return null;
        }
        $serviceUrl = rtrim(env('APP_URL'), '/');
        $currentUrlParts = explode('?', $currentUrl);
        if (isset($currentUrlParts[1])) {
            return "$serviceUrl/api/v1/notifications?{$currentUrlParts[1]}";
        }
        return "$serviceUrl/api/v1/notifications";
    }
}
