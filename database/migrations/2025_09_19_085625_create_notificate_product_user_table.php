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
            
            $table->unique(['product_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
      Schema::table('notificate_product_user', function (Blueprint $table) {
        $table->dropForeign(['product_id', 'user_id']);
         $table->dropForeign(['user_id']);
         $table->dropForeign(['product_id']);
      });
           
        Schema::dropIfExists('notificate_product_user');
    }
};
