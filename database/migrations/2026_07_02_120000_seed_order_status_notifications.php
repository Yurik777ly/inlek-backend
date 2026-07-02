<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $textsByAlias = [
        'order.status.processing' => 'Мы получили ваш заказ и уже приступаем к работе с ним.',
        'order.status.ready' => 'Заказ ждет вас в аптеке до конца дня',
        'order.status.reserved' => 'Проверим наличие и свяжемся с вами в случае отсутствия товаров.',
        'order.status.received' => 'Спасибо за заказ!',
        'order.status.staffed' => 'Ваш заказ собран, скоро мы передадим его курьеру.',
        'order.status.courier' => 'Курьер уже едет к вам.',
        'order.status.payment' => 'Товары готовы к сборке и ожидают оплаты.',
    ];

    public function up(): void
    {
        $statuses = DB::table('evo_commerce_order_statuses')
            ->whereIn('alias', array_keys($this->textsByAlias))
            ->get(['id', 'alias']);

        foreach ($statuses as $status) {
            $text = $this->textsByAlias[$status->alias] ?? null;
            if ($text === null) {
                continue;
            }

            $exists = DB::table('order_status_notifications')
                ->where('status_id', $status->id)
                ->exists();

            if ($exists) {
                DB::table('order_status_notifications')
                    ->where('status_id', $status->id)
                    ->update(['text' => $text]);
            } else {
                DB::table('order_status_notifications')->insert([
                    'status_id' => $status->id,
                    'text' => $text,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Данные справочные, откат не требуется.
    }
};
