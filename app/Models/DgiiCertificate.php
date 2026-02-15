<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class DgiiCertificate extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_default'      => 'boolean',
        'has_private_key' => 'boolean',
        'password_ok'     => 'boolean',
        'valid_from'      => 'datetime',
        'valid_to'        => 'datetime',
        // OJO: no dependas solo de 'array' porque tienes rows con string
        // 'meta'         => 'array',
    ];

    protected function meta(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                // Si viene null
                if ($value === null) return [];

                // Si ya viene array (casts o driver)
                if (is_array($value)) return $value;

                // Si viene string JSON (tu caso)
                if (is_string($value) && trim($value) !== '') {
                    $decoded = json_decode($value, true);
                    return is_array($decoded) ? $decoded : [];
                }

                return [];
            },
            set: function ($value) {
                // Siempre guardar como objeto/array normalizable
                if ($value === null) return null;
                if (is_array($value)) return $value;

                // Si te pasan string JSON, intenta decodificar y guardar como array
                if (is_string($value) && trim($value) !== '') {
                    $decoded = json_decode($value, true);
                    return is_array($decoded) ? $decoded : [];
                }

                return [];
            }
        );
    }
}
