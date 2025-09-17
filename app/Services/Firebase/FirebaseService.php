<?php

namespace App\Services\Firebase;

use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\FirebaseException;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class FirebaseService
{
    protected $messaging;

    public function __construct(Messaging $messaging)
    {
        $this->messaging = $messaging;
    }

   
    public function sendToDevice(string $deviceToken, array $notificationData, array $data = [])
    {
        try {
            $message = CloudMessage::withTarget('token', $deviceToken)
                ->withNotification(Notification::create(
                    $notificationData['title'] ?? '',
                    $notificationData['body'] ?? ''
                ));

            if (!empty($data)) {
                $message = $message->withData($data);
            }

            $response = $this->messaging->send($message);
        
            return [
                'success' => true,
                'message_id' => $response['name'],
            ];
        } catch (MessagingException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        } catch (FirebaseException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }


}