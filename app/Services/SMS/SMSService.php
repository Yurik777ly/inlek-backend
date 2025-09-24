<?php

namespace App\Services\SMS;

use App\Http\Dto\Auth\AuthDTO;
use App\Models\SMS;
use App\Providers\SMS\SMSProvider;
use Faker\Generator;
use DateTime;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use DateMalformedStringException;
use stdClass;
class SMSService
{

    public function __construct(
        protected readonly SMS $SMS,
        protected readonly SMSProvider $SMSProvider
    ) {}

    /**
     * Generate SMS code
     */
    public static function generateCode(): string
    {
        return (string) app(Generator::class)->randomNumber((int)env('SMS_DIGITS_QTY', 4), true);
    }

    public function sendSMS(AuthDTO $authDTO): array
    {
        $possibility = $this->checkGetSMSPossibility($authDTO);
        $sentStatus = [
            'sent' => false,
            'error' => $possibility['error']
        ];
        if ($possibility['possible']) {
            $this->saveSMSCode($authDTO);
            $smscode = $this->SMS->query()->where('phone', $authDTO->phone)->firstOrFail();
            $authDTO->code = $smscode->code;
            $sentStatus = $this->SMSProvider->sendSMS($authDTO);
        }
        return $sentStatus;
    }

    /**
     * Save SMS code to database
     */
    public function saveSMSCode(AuthDTO $authDTO): void
    {
        $sms = $this->SMS->query()->updateOrCreate(
            ['phone' => $authDTO->phone],
            ['code' => $authDTO->code ?? self::generateCode(), 'last_sms_requested_at' => date('Y-m-d h:i:s', time())]
        );
        $sms->increment('sms_requested_qty');
    }

    public function checkGetSMSPossibility(AuthDTO $authDTO): array
    {
        $possibility = [
            'possible' => true,
            'error' => ''
        ];
        try {
            $sms = $this->SMS->query()->where('phone', $authDTO->phone)->firstOrFail();
            $now = new DateTime;
            $last = null;
            try {
                $last = new DateTime($sms->last_sms_requested_at);
            } catch (DateMalformedStringException $e) {

            }
            $diffSeconds = $now->getTimestamp() - $last->getTimestamp();
            $diff = $now->diff($last);

            if ($diff->d >= 1) {
                $sms->sms_requested_qty = 0;
                $sms->save();
            }

            if (
                $diffSeconds < (int)env('SMS_TIMEOUT', 60)
                || $sms->sms_requested_qty + 1 > (int)env('SMS_TOTAL_PER_DAY', 3)
            ) {
                $possibility = [
                    'possible' => false,
                    'error' => 'Слишком частая отправка кода или превышено число попыток за день'
                ];
            }

        } catch (ModelNotFoundException) {
        }
        return $possibility;
    }

    public function confirmCode(AuthDTO $authDTO): array
    {
        $confirmation = [
            'success' => true,
            'error' => '',
        ];
        try {
            $sms = $this->SMS->query()->where('phone', $authDTO->phone)->firstOrFail();
            if (! $sms->is_confirmed) {
                if ($sms->code == $authDTO->code) {
                    $sms->is_confirmed = true;
                    $sms->save();
                } else {
                    $confirmation = [
                        'success' => false,
                        'error' => 'Код не совпал',
                    ];
                }

            }
        } catch (ModelNotFoundException) {
            $confirmation = [
                'success' => false,
                'error' => 'Подтвердите номер через СМС',
            ];
        }
        return $confirmation;
    }

    public function checkCodeIsConfirmed(AuthDTO $authDTO): bool
    {
        $sms = (object)['is_confirmed' => 0];
        try {
            $sms = $this->SMS->query()->where('phone', $authDTO->phone)->firstOrFail();
        } catch (ModelNotFoundException) {
        }
        return $sms->is_confirmed;
    }

    /**
     * Delete SMS records for phone from database
     */
    public function clearSMSCode(AuthDTO $authDTO): void
    {
        $this->SMS->query()->where('phone', $authDTO->phone)->delete();
    }

    public function checkCodeExists(AuthDTO $authDTO): bool
    {
        $exists = false;
        try {
            $sms = $this->SMS->query()->where('phone', $authDTO->phone)->firstOrFail();
            $exists = true;
        } catch (ModelNotFoundException) {
        }

        return $exists;
    }

}
