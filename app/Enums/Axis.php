<?php

namespace App\Enums;

/**
 * A live relationship axis on a directed edge (ADR 0002).
 *
 * The fixed set of dimensions the delta engine and decay operate on per-axis.
 * Mirrors the `edge_axes.axis` and `axis_deltas.axis` DB enums.
 */
enum Axis: string
{
    case Affection = 'affection';
    case Trust = 'trust';
    case Fear = 'fear';
    case Respect = 'respect';
    case Romantic = 'romantic';
    case Rivalry = 'rivalry';
    case Debt = 'debt';
}
