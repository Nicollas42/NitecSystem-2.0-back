<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends Authenticatable
{
    // 2. IMPORTANTE: Adicione 'HasApiTokens' dentro da classe
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'usuarios'; // Sua tabela personalizada

    protected $fillable = [
        'nome_completo',
        'email',
        'celular',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verificado_em' => 'datetime',
            'password' => 'hashed',
        ];
    }
}