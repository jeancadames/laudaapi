<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class DgiiTransmission extends Model
{
    use HasUlids;

    protected $table = 'dgii_transmissions';

    // ✅ id sigue siendo BIGINT autoincrement (NO ULID)
    public $incrementing = true;
    protected $keyType = 'int';

    /**
     * ✅ Dile a HasUlids que el ULID es para public_id, NO para id
     * (esto evita que intente insertar un ULID en la PK)
     */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    protected $fillable = [
        'public_id',
        'company_id',
        'fiscal_document_id',
        'endpoint_key',
        'environment',

        'signed_xml_path',
        'signed_xml_sha256',

        'request_body_path',
        'request_sha256',
        'request_content_type',
        'request_size_bytes',

        'url',
        'http_method',

        'http_status',
        'response_body_path',
        'response_sha256',
        'response_content_type',
        'response_size_bytes',

        'dgii_codigo',
        'dgii_estado',
        'dgii_track_id',
        'dgii_mensajes',

        'status',
        'attempt',
        'idempotency_key',

        'duration_ms',
        'sent_at',
        'received_at',

        'error_message',
        'meta',
    ];

    protected $casts = [
        'dgii_mensajes' => 'array',
        'meta' => 'array',
        'sent_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    // ✅ opcional: si quieres que SIEMPRE se genere aunque no lo mandes
    protected static function booted(): void
    {
        static::creating(function (self $m) {
            if (empty($m->public_id)) {
                $m->public_id = $m->newUniqueId(); // ULID string
            }
        });
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function fiscalDocument()
    {
        return $this->belongsTo(FiscalDocument::class, 'fiscal_document_id');
    }
}