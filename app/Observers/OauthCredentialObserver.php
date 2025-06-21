<?php

namespace App\Observers;

use App\Models\OauthCredential;
use App\Notifications\OauthCredentialCreated;

class OauthCredentialObserver
{
    public function created(OauthCredential $oauthCredential): void
    {
        if ($oauthCredential->provider === OauthCredential::GOOGLE) {
            return;
        }

        $oauthCredential->user()->firstOrFail()->notify(new OauthCredentialCreated($oauthCredential));
    }
}
