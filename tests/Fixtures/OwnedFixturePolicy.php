<?php

namespace Tests\Fixtures;

use App\Policies\OwnerPolicy;

/**
 * Concrete owner policy for the OwnedFixture, used to assert the abstract
 * {@see OwnerPolicy} denies cross-owner access by ownership alone.
 */
class OwnedFixturePolicy extends OwnerPolicy {}
