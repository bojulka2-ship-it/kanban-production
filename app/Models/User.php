<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'role',
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
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * Цвет-бейдж для кружка-инициалов: стабилен для одного имени (из хэша),
     * разные пользователи получают разные цвета из палитры.
     */
    public function getAvatarColorAttribute(): string
    {
        $palette = [
            'bg-blue-100 text-blue-700',
            'bg-emerald-100 text-emerald-700',
            'bg-amber-100 text-amber-700',
            'bg-fuchsia-100 text-fuchsia-700',
            'bg-sky-100 text-sky-700',
            'bg-rose-100 text-rose-700',
            'bg-violet-100 text-violet-700',
            'bg-cyan-100 text-cyan-700',
        ];
        $index = hexdec(substr(md5($this->name ?? ''), 0, 4)) % count($palette);

        return $palette[$index];
    }

    /**
     * Инициалы для карточки доски (первые буквы имени и фамилии).
     */
    public function getInitialsAttribute(): string
    {
        $parts = preg_split('/\s+/', trim($this->name ?? ''));
        $out = '';
        foreach (array_slice($parts ?: [], 0, 2) as $part) {
            $out .= mb_strtoupper(mb_substr($part, 0, 1));
        }

        return $out !== '' ? $out : '?';
    }

    /**
     * Задачи, за которые пользователь отвечает.
     */
    public function projectTasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class, 'responsible_id');
    }

    /**
     * Записи журнала перемещений пользователя.
     */
    public function taskHistory(): HasMany
    {
        return $this->hasMany(TaskHistory::class);
    }

    /**
     * Комментарии пользователя.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }
}