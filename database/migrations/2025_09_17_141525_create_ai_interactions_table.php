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
        Schema::create('ai_interactions', function (Blueprint $table) {
            $table->id();
            $table->string('conversation_id')->index();
            $table->string('type')->index(); // 'request', 'response', 'function_call', 'function_result'
            $table->json('content'); // Полное содержимое запроса/ответа
            $table->string('model')->nullable();
            $table->string('function_name')->nullable()->index();
            $table->json('function_arguments')->nullable();
            $table->json('function_result')->nullable();
            $table->integer('tokens_used')->nullable();
            $table->decimal('cost', 10, 6)->nullable();
            $table->string('status')->default('success'); // success, error, pending
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_interactions');
    }
};
