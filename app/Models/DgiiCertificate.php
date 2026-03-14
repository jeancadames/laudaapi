<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class DgiiCertificate extends Model
{
    /**
     * ✅ Evita que cualquier key random entre por mass-assignment
     * (tu bug nace por guarded = [])
     */
    protected $guarded = ['id'];

    protected $casts = [
        'is_default'      => 'boolean',
        'has_private_key' => 'boolean',
        'password_ok'     => 'boolean',
        'valid_from'      => 'datetime',
        'valid_to'        => 'datetime',
    ];

    /**
     * ✅ AIRBAG: si por cualquier razón aparecen atributos no existentes
     * (subject, issuer, container, etc.), muévelos a meta y elimínalos
     * antes de INSERT/UPDATE para que NUNCA rompa.
     */
    protected static function booted(): void
    {
        static::creating(function (self $m) {
            if ($m->type !== 'p12') {
                $m->is_default = false;
            }

            $m->packUnknownAttributesIntoMeta();
        });

        static::updating(function (self $m) {
            if ($m->type !== 'p12' && $m->is_default) {
                $m->is_default = false;
            }

            $m->packUnknownAttributesIntoMeta();
        });
    }

    private function packUnknownAttributesIntoMeta(): void
    {
        // Columnas reales según tu migración
        $allowed = [
            'id',
            'company_id',
            'label',
            'type',
            'is_default',

            'file_disk',
            'file_path',
            'original_name',
            'file_size',
            'file_sha256',

            'subject_cn',
            'subject_rnc',
            'issuer_cn',
            'serial_number',
            'valid_from',
            'valid_to',

            'has_private_key',
            'password_ok',
            'meta',
            'status',

            'created_at',
            'updated_at',
        ];

        $attrs = $this->getAttributes();
        $unknown = array_diff(array_keys($attrs), $allowed);

        if (!$unknown) return;

        $meta = (array) ($this->meta ?? []);

        foreach ($unknown as $k) {
            $meta[$k] = $attrs[$k];
            unset($this->attributes[$k]);
        }

        $this->meta = $meta;
    }

    /**
     * ✅ Meta robusta:
     * - Si viene string JSON en DB, lo decodifica.
     * - Si te asignan array, lo guarda.
     * - Siempre persiste como JSON string para consistencia.
     */
    protected function meta(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if ($value === null) return [];

                if (is_array($value)) return $value;

                if (is_string($value) && trim($value) !== '') {
                    $decoded = json_decode($value, true);
                    return is_array($decoded) ? $decoded : [];
                }

                return [];
            },
            set: function ($value) {
                if ($value === null) return null;

                if (is_string($value) && trim($value) !== '') {
                    $decoded = json_decode($value, true);
                    $value = is_array($decoded) ? $decoded : [];
                }

                if (!is_array($value)) $value = [];

                // 👇 Fuerza persistencia consistente (evita mezcla array/string)
                return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        );
    }
}
