<?php

namespace App\Services\User;

use App\Http\Dto\Auth\AuthDTO;
use App\Http\Dto\Profile\ProfileDTO;
use App\Services\SMS\SMSService;
use App\Services\User\UserService;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

use App\Services\Notifications\FireBase;

class AuthService
{
    public function __construct(
        protected readonly SMSService $SMSService,
        protected readonly User $User,
        protected readonly UserService $UserService,
    ) {}

    /**
     * @param AuthDTO $authDTO
     * @return array
     */
    public function createUser(AuthDTO $authDTO): array
    {
        $confirmed = $this->SMSService->checkCodeIsConfirmed($authDTO);
        if (!$confirmed) {
            return [
                'status' => false,
                'error' => 'Код из СМС не подтвержден',
            ];
        }
        $data = $this->UserService->createUser($authDTO);
        if ($data['status']) {
            //$this->SMSService->clearSMSCode($authDTO);
        }
        return $data;
    }

    /**
     * @param AuthDTO $authDTO
     * @return array
     */
    public function updatePassword(AuthDTO $authDTO): array
    {
        $confirmed = $this->SMSService->checkCodeIsConfirmed($authDTO);
        if (!$confirmed) {
            return [
                'status' => false,
                'error' => 'Код из СМС не подтвержден',
            ];
        }
        $data = $this->UserService->updatePassword($authDTO);
        if ($data['status']) {
            $this->SMSService->clearSMSCode($authDTO);
            $user = $this->User->query()->where('phone', $authDTO->phone)->first();
            $user->tokens()->delete();
        }
        return $data;
    }

    /**
     * Login
     * @param AuthDTO $authDTO
     * @return ?array
     */
    public function getUser(AuthDTO|ProfileDTO $dto): ?User
    {
        return $this->User->query()->where('phone', $dto->phone)->first();
    }

    /**
     * Login
     * @param AuthDTO $authDTO
     * @return ?array
     * @throws ValidationException
     */
    public function login(AuthDTO $authDTO): ?array
    {
        $user = $this->getUser($authDTO);
        if(!$user) {
            throw ValidationException::withMessages([
                'phone' => ['телефон не найден'],
            ]);
        }
        if (!Hash::check($authDTO->password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['пароль введен неверно'],
            ]);
        }

        if (!empty($authDTO->fcm_token)) {
            try {
                $this->UserService->updateFcmToken($authDTO);
                $user = $this->getUser($authDTO);
            } catch (\Exception $e) {
                \Log::warning('Failed to update FCM token during login', [
                    'phone' => $authDTO->phone,
                    'error' => $e->getMessage()
                ]);
            }
        }

        if (!empty($user->fcm_token)) {
            try {
                FireBase::send(
                    'Авторизация',
                    'Совершен вход в личный кабинет',
                    [$user->fcm_token],
                    []
                );
            } catch (\Exception $e) {
                \Log::warning('Failed to send Firebase notification during login', [
                    'phone' => $authDTO->phone,
                    'fcm_token' => $user->fcm_token,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $token = $user->createToken($user->phone);
        return [
            'access_token' => $token->plainTextToken,
        ];
    }

    /**
     * @param User $user
     * @return bool
     */
    public function logout(User $user): bool
    {
        return $user->tokens()->delete();
    }
}
