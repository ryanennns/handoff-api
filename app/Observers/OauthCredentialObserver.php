<?php

namespace App\Observers;

use App\Events\NewOauthCredential;
use App\Models\OauthCredential;

class OauthCredentialObserver
{
    public function created(OauthCredential $oauthCredential): void
    {
        if ($oauthCredential->provider === OauthCredential::GOOGLE) {
            return;
        }

        NewOauthCredential::dispatch($oauthCredential);
    }
}
