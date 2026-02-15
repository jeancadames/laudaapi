<?php

namespace App\Services\Dgii;

use App\Models\DgiiCompanySetting;

interface DgiiAuthClient
{
    /** @return array{token:string, expires_in:int} */
    public function requestToken(DgiiCompanySetting $setting): array;
}
