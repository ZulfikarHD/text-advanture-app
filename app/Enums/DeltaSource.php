<?php

namespace App\Enums;

/**
 * What produced an axis delta (ADR 0003/0004).
 *
 * The provenance of an append-only delta row: an appraisal, a rupture event,
 * scheduled decay, a human review edit, or a manual authoring change. Mirrors
 * the `axis_deltas.source` DB enum.
 */
enum DeltaSource: string
{
    case Appraisal = 'appraisal';
    case Rupture = 'rupture';
    case Decay = 'decay';
    case ReviewEdit = 'review_edit';
    case Manual = 'manual';
}
