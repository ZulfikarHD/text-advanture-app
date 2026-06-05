import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { ComputedRef } from 'vue';

/**
 * Project display standards shared from the backend (see
 * `App\Http\Middleware\HandleInertiaRequests`). Storage is always UTC; these
 * values only drive how data is rendered to the user.
 */
export type AppStandards = {
    /** IANA timezone used for wall-clock rendering (e.g. `Asia/Jakarta`). */
    timezone: string;
    /** BCP-47 locale used for date/number formatting (e.g. `id-ID`). */
    locale: string;
    /** ISO-4217 currency code rendered to the user (e.g. `IDR`). */
    currency: string;
};

export type DateInput = Date | string | number | null | undefined;

export type UseFormatReturn = {
    standards: ComputedRef<AppStandards>;
    formatDateTime: (value: DateInput, options?: Intl.DateTimeFormatOptions) => string;
    formatDate: (value: DateInput, options?: Intl.DateTimeFormatOptions) => string;
    formatCurrency: (value: number | null | undefined, options?: Intl.NumberFormatOptions) => string;
};

/**
 * Fallbacks used when the shared standards are unavailable (e.g. an error page
 * rendered outside the Inertia middleware). Mirrors `config/app.php`.
 */
const FALLBACK_STANDARDS: AppStandards = {
    timezone: 'Asia/Jakarta',
    locale: 'id-ID',
    currency: 'IDR',
};

/**
 * Format a timestamp as wall-clock time in a given timezone (WIB by default).
 *
 * UTC is the storage standard; callers pass the raw stored value and this
 * renders it in the user-facing timezone. Returns an empty string for nullish
 * or unparseable input so it is safe to use directly in templates.
 *
 * @param value - A Date, ISO string, or epoch milliseconds (UTC stored).
 * @param options - Intl.DateTimeFormat overrides; `locale`/`timeZone` may be set.
 * @returns The localized date-time string, or `''` when input is invalid.
 */
export function formatDateWib(
    value: DateInput,
    options: Intl.DateTimeFormatOptions & { locale?: string } = {},
): string {
    if (value === null || value === undefined || value === '') {
        return '';
    }

    const date = value instanceof Date ? value : new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    const { locale = FALLBACK_STANDARDS.locale, ...formatOptions } = options;

    return new Intl.DateTimeFormat(locale, {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: FALLBACK_STANDARDS.timezone,
        ...formatOptions,
    }).format(date);
}

/**
 * Format a numeric amount as currency (Rupiah by default).
 *
 * Provider cost is stored as the provider-reported value; this is display only.
 * Returns an empty string for nullish input so tables can show a placeholder.
 *
 * @param value - The numeric amount in major currency units.
 * @param options - Intl.NumberFormat overrides; `locale`/`currency` may be set.
 * @returns The localized currency string, or `''` when input is nullish.
 */
export function formatRupiah(
    value: number | null | undefined,
    options: Intl.NumberFormatOptions & { locale?: string } = {},
): string {
    if (value === null || value === undefined || Number.isNaN(value)) {
        return '';
    }

    const { locale = FALLBACK_STANDARDS.locale, ...formatOptions } = options;

    return new Intl.NumberFormat(locale, {
        style: 'currency',
        currency: FALLBACK_STANDARDS.currency,
        ...formatOptions,
    }).format(value);
}

/**
 * Format a provider cost (stored as USD micro-units) as a USD amount.
 *
 * LLM/provider cost is rendered in USD - not converted to Rupiah - because an
 * OpenRouter balance is held in USD, so showing USD keeps spend reconcilable
 * against the provider dashboard (PH-12). Uses up to 6 fraction digits so
 * sub-cent per-call costs are still legible. Returns `''` for nullish input.
 *
 * @param micros - Provider cost in USD micro-units (1e-6 USD), or nullish.
 * @returns The USD currency string (e.g. `$0.001234`), or `''` when nullish.
 * @example
 * formatUsdFromMicros(1234)  // "$0.001234"
 * formatUsdFromMicros(50000) // "$0.05"
 */
export function formatUsdFromMicros(micros: number | null | undefined): string {
    if (micros === null || micros === undefined || Number.isNaN(micros)) {
        return '';
    }

    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 2,
        maximumFractionDigits: 6,
    }).format(micros / 1_000_000);
}

/**
 * Reactive formatting bound to the backend display standards.
 *
 * Reads `timezone`, `locale`, and `currency` from shared Inertia props so the
 * whole app formats time in WIB and money in Rupiah consistently.
 *
 * @returns Standards plus `formatDateTime`, `formatDate`, and `formatCurrency`.
 * @example
 * const { formatDateTime, formatCurrency } = useFormat()
 * formatDateTime(user.created_at) // "5 Jun 2026, 08.56"
 * formatCurrency(15000)           // "Rp 15.000"
 */
export function useFormat(): UseFormatReturn {
    const page = usePage();

    const standards = computed<AppStandards>(() => ({
        ...FALLBACK_STANDARDS,
        ...((page.props.standards as Partial<AppStandards> | undefined) ?? {}),
    }));

    function formatDateTime(value: DateInput, options: Intl.DateTimeFormatOptions = {}): string {
        return formatDateWib(value, {
            locale: standards.value.locale,
            timeZone: standards.value.timezone,
            ...options,
        });
    }

    function formatDate(value: DateInput, options: Intl.DateTimeFormatOptions = {}): string {
        return formatDateTime(value, { dateStyle: 'medium', timeStyle: undefined, ...options });
    }

    function formatCurrency(value: number | null | undefined, options: Intl.NumberFormatOptions = {}): string {
        return formatRupiah(value, {
            locale: standards.value.locale,
            currency: standards.value.currency,
            ...options,
        });
    }

    return { standards, formatDateTime, formatDate, formatCurrency };
}
