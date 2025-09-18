<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'username',
        'telegram_id',
        'conversation_id',
        'conversation_updated_at',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'conversation_updated_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Scope для активных пользователей
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function active($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope для пользователей Telegram
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function telegram($query)
    {
        return $query->whereNotNull('telegram_id');
    }

    /**
     * Проверяет, является ли пользователь активным
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Очищает данные conversation пользователя
     */
    public function clearConversationData(): void
    {
        $this->update([
            'conversation_id' => null,
            'conversation_updated_at' => null,
        ]);
    }
}
