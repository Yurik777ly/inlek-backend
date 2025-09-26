<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notificate_product_user', function (Blueprint $table) {
            
            $table->id();
            $table->unsignedInteger('product_id'); 
            $table->unsignedBigInteger('user_id');
            $table->timestamp('notified_at')->nullable();
        
        
            $table->foreign('product_id')
              ->references('id')
              ->on('evo_site_content')
              ->onDelete('cascade');
              
            $table->foreign('user_id')
              ->references('id')
              ->on('users')
              ->onDelete('cascade');
            // Сообщать о пуступлении в конкретную аптеку или нет?
            // $table->integer('pharmacy_id')->nullable();
            
            $table->unique(['product_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notificate_product_user');
    }
};
