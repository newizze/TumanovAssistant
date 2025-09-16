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
        Schema::table('users', function (Blueprint $table) {
            $table->bigInteger('telegram_id')->unique()->nullable();
            $table->string('username')->nullable();
            $table->string('conversation_id')->nullable();
            $table->timestamp('conversation_updated_at')->nullable();
            $table->boolean('is_active')->default(false);
            
            // Делаем email опциональным для пользователей Telegram
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
            
            $table->index('telegram_id');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Возвращаем обратно обязательность email и password
            $table->string('email')->nullable(false)->change();
            $table->string('password')->nullable(false)->change();
            
            $table->dropIndex(['telegram_id']);
            $table->dropIndex(['is_active']);
            $table->dropColumn([
                'telegram_id',
                'username', 
                'conversation_id',
                'conversation_updated_at',
                'is_active'
            ]);
        });
    }
};
