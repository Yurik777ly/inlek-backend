<?php

namespace App\Services\User;

use Carbon\Carbon;
use App\Http\Dto\Auth\AuthDTO;
use App\Http\Dto\Profile\ProfileDTO;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserService
{
    public function __construct(
        protected readonly User $User,
    ) {}

    /**
     * @param AuthDTO $authDTO
     * @return array
     * @throws ValidationException
     */
    public function createUser(AuthDTO $authDTO): array
    {
        $possibility = [
            'status' => true,
            'error' => '',
        ];

        try {
            $user = $this->User->query()->where('phone', $authDTO->phone)->firstOrFail();
            throw ValidationException::withMessages([
                'phone' => 'номер телефона занят',
            ]);
        } catch (ModelNotFoundException) {
            $this->User->password = Hash::make($authDTO->password);
            $this->User->phone = $authDTO->phone;
            $this->User->accept_policy = true;
            $this->User->status_notifications = true;
            $this->User->name = 'Пользователь';
            $this->User->save();
        }

        return $possibility;
    }

    /**
     * @param AuthDTO $authDTO
     * @return array
     * @throws ValidationException
     */
    public function updatePassword(AuthDTO $authDTO): array
    {
        $possibility = [
            'status' => true,
            'error' => '',
        ];

        try {
            $user = $this->User->query()->where('phone', $authDTO->phone)->firstOrFail();
            if (Hash::check($authDTO->password, $user->password)) {
                throw ValidationException::withMessages([
                    'password' => 'Пароль должен отличатся от старого',
                ]);
            }
            $user->password = Hash::make($authDTO->password);
            $user->save();
        } catch (ModelNotFoundException) {
            throw ValidationException::withMessages([
                'phone' => 'Аккаунта с таким номером не существует'
            ]);
        }

        return $possibility;
    }


    public function updateFcmToken(AuthDTO $authDTO): void
    {
        try {
            $user = $this->User->query()->where('phone', $authDTO->phone)->firstOrFail();
            $user->fcm_token = $authDTO->fcm_token;
            $user->save();
        } catch (ModelNotFoundException) {
            throw ValidationException::withMessages([
                'phone' => 'Аккаунта с таким номером не существует'
            ]);
        }
    }

    /**
     * @param ProfileDTO $profileDTO
     * @return array
     */
    public function checkFormChanges(ProfileDTO $profileDTO): array
    {
        $changes = ['phone' => false, 'password' => false, 'anything' => false, 'error' => ''];
        try {
            $user = $this->User->query()->where('id', $profileDTO->userId)->firstOrFail();

            if ($profileDTO->oldPassword != '') {
//????
                if ($profileDTO->oldPassword == $profileDTO->newPassword) {
                    $changes['error'] = 'Старый и новый пароли совпадают';
                } else if ($profileDTO->newPasswordConfirm != $profileDTO->newPassword) {
                    $changes['error'] = 'Несовпадение нового пароля с подтвержденным';
                } else if (!Hash::check($profileDTO->oldPassword, $user->password)) {
                    $changes['error'] = 'Текущий пароль пользователя указан неверно';
                }
            } else {
                if ($profileDTO->email != '' && !filter_var($profileDTO->email, FILTER_VALIDATE_EMAIL)) {
                    $changes['error'] = '';
                } else if (!preg_match('/^\+375(25|29|33|44)-\d{3}-\d{2}\-\d{2}$/', $profileDTO->phone)) {
                    $changes['error'] = 'Введите правильный номер телефона';
                } else if (!$profileDTO->acceptPolicy) {
                    $changes['error'] = 'Примите условия политики обработки персональных данных';
                } else if (!in_array($profileDTO->gender, ['male', 'female'])) {
                    $changes['error'] = 'Выберите пол';
                } else if ($profileDTO->birthday != '' && !preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $profileDTO->birthday)) {
                    $changes['error'] = 'Введите правильную дату рождения';
                } else if ($profileDTO->phone == '') {
                    $changes['error'] = 'Не заполнен телефон';
                }
            }

            if ($changes['error'] == '') {
                if ($profileDTO->phone != $user->phone) {
                    $changes['phone'] = true;
                    $changes['anything'] = true;
                }

                if ($changes['password'] && !Hash::check($profileDTO->newPassword, $user->password)) {
                    $changes['password'] = true;
                    $changes['anything'] = true;
                }

                if (
                    $user->first_name != $profileDTO->firstName ||
                    $user->last_name != $profileDTO->lastName ||
                    $user->email != $profileDTO->email ||
                    $user->birthday != $profileDTO->birthday ||
                    $user->gender != $profileDTO->gender
                )
                {
                    $changes['anything'] = true;
                }
            }

        }
        catch (ModelNotFoundException) {
            $changes['error'] = 'Пользователь не найден';
        }
        return $changes;
    }

    /**
     * @param ProfileDTO $profileDTO
     * @return array
     */
    public function updateUser(ProfileDTO $profileDTO): array
    {
        $updated = ['success' => false, 'error' => ''];
        try {
            $user = $this->User->query()->where('id', $profileDTO->userId)->firstOrFail();
            $user->update(
                [
                    'phone' => $profileDTO->phone,
                    'name' => $profileDTO->firstName . ' ' . $profileDTO->lastName,
                    'first_name' => $profileDTO->firstName,
                    'last_name' => $profileDTO->lastName,
                    'gender' => $profileDTO->gender,
                    'birthday' => $profileDTO->birthday,
                    'email' => $profileDTO->email,
                    'accept_policy' => $profileDTO->acceptPolicy,
                    'status_notifications' => $profileDTO->statusNotifications,
                ]
            );
            $updated['success'] = true;
        }
        catch (ModelNotFoundException) {
            $updated = ['success' => false, 'error' => 'Пользователь не найден'];
        }
        return $updated;
    }
}
