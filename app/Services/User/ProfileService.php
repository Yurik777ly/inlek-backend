<?php

namespace App\Services\User;

use App\Http\Dto\Auth\AuthDTO;
use App\Services\SMS\SMSService;
use App\Providers\SMS\SMSProvider;
use App\Http\Dto\Profile\ProfileDTO;
use App\Models\User;
use App\Models\SMS;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ProfileService
{
    public function __construct(
        protected readonly SMSProvider $SMSProvider,
        protected readonly SMSService $SMSService,
        protected readonly UserService $UserService,
        protected readonly User $User,
        protected readonly SMS $SMS,
    ) {}

    /**
     * @param ProfileDTO $profileDTO
     * @return ProfileDTO|null
     */
    public function getUserProfile(ProfileDTO $profileDTO): ?ProfileDTO
    {
        $profile = new ProfileDTO(userId: 0, phone: '');
        try {
            $user = $this->User->query()->where('id', $profileDTO->userId)->firstOrFail();
            $profile = new ProfileDTO(
                userId: $user->id,
                phone: $user->phone,
                firstName: $user->first_name,
                lastName: $user->last_name,
                gender: $user->gender,
                birthday: $user->birthday,
                email: $user->email,
                statusNotifications: $user->status_notifications,
                acceptPolicy: $user->accept_policy
            );
        }
        catch (ModelNotFoundException) {
        }
        return $profile;
    }

    /**
     * @param ProfileDTO $profileDTO
     * @return array
     */
    public function updateProfile(ProfileDTO $profileDTO): array
    {
        $result = ['success' => true, 'error' => ''];
        $checkForm = $this->UserService->checkFormChanges($profileDTO);
        if ($checkForm['error'] != '') {
            $result = ['success' => false, 'error' => $checkForm['error']];
        } else {
            if ($checkForm['phone']) {
                $authDTO = new AuthDTO(phone: $profileDTO->phone, code:$profileDTO->code);
                $codeExists = $this->SMSService->checkCodeExists($authDTO);

                if (!$codeExists) {
                    $possibility = $this->SMSService->checkGetSMSPossibility($authDTO);

                    if (!$possibility['possible']) {
                        $result = ['success' => false, 'error' => $possibility['error']];
                    }
                    else {
                        $this->SMSService->saveSMSCode($authDTO);
                        $sendStatus = $this->SMSProvider->sendSMS($authDTO);
                        if ($sendStatus['sent']) {
                            $sms = $this->SMS->query()->where('phone', $authDTO->phone)->firstOrFail();
                            $result = [
                                'success' => false,
                                'error' => 'SMS Код отправлен',
                                'code' => $sms->code
                            ];
                        } else {
                            $result = [
                                'success' => false,
                                'error' => 'SMS код не отправлен',
                            ];
                        }
                    }

                }
                else {
                    $confirmed = $this->SMSService->checkCodeIsConfirmed($authDTO);
                    if (!$confirmed) {
                        if(!empty($authDTO->code)) {
                            $dataSMS = $this->SMSService->confirmCode($authDTO);
                            if (!$dataSMS['success']) {
                                $result = [
                                    'success' => false,
                                    'error' => 'Неверный СМС код'
                                ];
                            }
                        } else {
                                $result = [
                                    'success' => false,
                                    'error' => 'Введите код из СМС'
                                ];
                        }
                    }
                }
            }

            if ($result['success']) {
                $cnangesDTO = new AuthDTO(phone: $profileDTO->phone, password: $profileDTO->newPassword);
                $result = $this->UserService->updateUser($profileDTO);
                if ($checkForm['password']) {
                    $this->UserService->updatePassword($cnangesDTO);
                }
                if ($checkForm['phone']) {
                    $user = $this->User->query()->where('id', $profileDTO->userId)->firstOrFail();
                    $user->update(
                        [
                            'accept_policy' => false,
                        ]
                    );

                    $this->SMSService->clearSMSCode($cnangesDTO);
                }

            }

        }
        return $result;
    }

    /**
     * @param ProfileDTO $profileDTO
     */
    public function deleteProfile(User $user): void
    {
        /** @var User $user */
        $user = $this->User->query()->where('id', $user->id)->firstOrFail();
        $this->SMS->query()->where('phone', $user->phone)->delete();

/*
                userId: $user->id,
                phone: $user->phone,
                firstName: $user->first_name,
                lastName: $user->last_name,
                gender: $user->gender,
                birthday: $user->birthday,
                email: $user->email,
                statusNotifications: $user->status_notifications,
                acceptPolicy: $user->accept_policy
        );
*/
        $user->forceDelete();
    }
}
