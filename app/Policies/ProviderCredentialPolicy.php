<?php

namespace App\Policies;

use App\Models\ProviderCredential;

/**
 * Authorization policy for {@see ProviderCredential}.
 *
 * Authorization is by ownership alone via the base {@see OwnerPolicy} - a user
 * may only view, update, or delete their own provider credential. Resolved
 * automatically by Laravel's policy auto-discovery.
 */
class ProviderCredentialPolicy extends OwnerPolicy {}
