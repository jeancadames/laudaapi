<?php

use App\Models\ApiCredential;
use App\Models\CertificationProcess;
use App\Models\NcfSequence;
use App\Models\User;
use App\Models\VaultCertificate;
use Database\Seeders\DgiiEndpointCatalogSeeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

require __DIR__.'/ecf-bootstrap.php';
DB::statement('PRAGMA synchronous = OFF');
foreach (glob(base_path('database/migrations/*.php')) as $migration) {
    (require $migration)->up();
}
(new DgiiEndpointCatalogSeeder)->run();
$tenant = User::withoutEvents(fn () => User::forceCreate(['id' => 501, 'name' => 'Emisor sintético', 'email' => 'fiscal@example.test',
    'password' => Hash::make('Synthetic-only-password'), 'email_verified_at' => now(), 'rnc' => '101010101',
    'razon_social' => 'EMPRESA SINTETICA SIN VALIDEZ FISCAL', 'direccion_fiscal' => 'Dirección sintética', 'dgii_environment' => 'testecf', 'is_active' => true]));
app()->instance('tenant', $tenant);
CertificationProcess::forceCreate(['user_id' => 501, 'status' => 'aprobado', 'etapa_actual' => 15]);
ApiCredential::create(['user_id' => 501, 'name' => 'Synthetic Business', 'key' => 'synthetic-business-key', 'secret_hash' => Hash::make('synthetic-business-secret'),
    'environment' => 'test', 'scopes' => ['ecf:emit', 'ecf:read', 'ecf:cancel'], 'active' => true]);
foreach (['e31', 'e32', 'e33', 'e34', 'e43', 'e47'] as $type) {
    NcfSequence::create(['user_id' => 501, 'environment' => 'testecf', 'tipo_ncf' => $type, 'serie' => 'E', 'desde' => 1, 'hasta' => 100, 'siguiente' => 1, 'vence_el' => today()->addYear()]);
}
$key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
$csr = openssl_csr_new(['commonName' => 'SYNTHETIC-ONLY-101010101'], $key, ['digest_alg' => 'sha256']);
$cert = openssl_csr_sign($csr, null, $key, 2, ['digest_alg' => 'sha256']);
openssl_pkcs12_export($cert, $p12, $key, 'synthetic-only-passphrase');
Storage::disk('local')->put('synthetic/signing.p12.enc', Crypt::encryptString($p12));
$record = new VaultCertificate(['user_id' => 501, 'type' => 'tenant_signing', 'status' => 'activo', 'disk' => 'local',
    'path' => 'synthetic/signing.p12.enc', 'original_name' => 'SYNTHETIC.p12', 'subject_cn' => 'SYNTHETIC-ONLY',
    'valid_from' => now()->subMinute(), 'valid_until' => now()->addDays(2)]);
$record->setPassphrase('synthetic-only-passphrase');
$record->save();
DB::table('business_identity_links')->insert($fixture['links']['ecf']);
DB::statement('PRAGMA synchronous = FULL');
echo 'Synthetic fiscal schema ready';
