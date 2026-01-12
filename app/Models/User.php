<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'usuarios';

    public function notas_fiscais()
    {
        return $this->hasMany(NotaFiscal::class, 'user_id');
    }

    protected $fillable = [
        'empresa_id', // <--- CAMPO NOVO IMPORTANTÍSSIMO
        'nome_completo',
        'email',
        'celular',
        'password',
        'cpf'
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