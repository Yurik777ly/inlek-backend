<?php

namespace Database\Seeders;

use App\Models\EVO\EvoCommerceOrderStatuses;
use App\Models\OrderStatusNotification;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OrderStatusNotSeeder extends Seeder
{
    private $notificationStatuses = [
        'order.status.processing' => [
            'text' => 'Мы получили ваш заказ и уже приступаем к работе с ним.'
        ],
        'order.status.ready' => [
            'text' => 'Заказ ждет вас в аптеке до конца дня'
        ],
        'order.status.reserved' => [
            'text' => 'Проверим наличие и свяжемся с вами в случае отсутствия товаров.'
        ],
        'order.status.received' => [
            'text' => 'Спасибо за заказ!'
        ],
        'order.status.staffed' => [
            'text' => 'Ваш заказ собран, скоро мы передадим его курьеру.'
        ],
        'order.status.courier' => [
            'text' => 'Курьер уже едет к вам.'
        ],
        'order.status.payment' => [
            'text' => 'Товары готовы к сборке и ожидают оплаты.'
        ],
    ];

    public function run(): void
    {
        $commerceStatuses = EvoCommerceOrderStatuses::select(['alias', 'id'])->get();
    
        foreach ($commerceStatuses as $status) {
            if (isset($this->notificationStatuses[$status->alias])) {
                OrderStatusNotification::updateOrCreate(
                    ['status_id' => $status->id], 
                    ['text' => $this->notificationStatuses[$status->alias]['text']] 
                );
            }
        }
    }   
}
