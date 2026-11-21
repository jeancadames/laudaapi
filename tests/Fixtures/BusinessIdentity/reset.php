<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

$app = require __DIR__.'/bootstrap.php';
set_exception_handler(static function (Throwable $error): never {
    fwrite(STDERR, $error::class.': '.$error->getMessage().PHP_EOL);
    exit(2);
});
// Bootstrap verifies this is the dedicated synthetic database before any mutation.
foreach (['events', 'proofs', 'members', 'accounts', 'nonces', 'intents', 'links'] as $suffix) {
    DB::table('business_identity_'.$suffix)->delete();
}
Cache::flush();
echo getenv('LAUDA_BUSINESS_PROJECT')." identity test state reset\n";
