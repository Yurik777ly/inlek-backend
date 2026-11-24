<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Идемпотентность слияния гостевой корзины.
 * Ключ операции: user_id + merge_token + product_id.
 * Внешних ключей нет: запись должна пережить удаление товара.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_merge_operations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('merge_token', 64);
            $table->unsignedInteger('product_id');
            $table->unsignedTinyInteger('quantity');
            $table->boolean('limited');
            $table->timestamp('created_at')->nullable();

            $table->unique(
                ['user_id', 'merge_token', 'product_id'],
                'cart_merge_operations_user_token_product_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_merge_operations');
    }
};
