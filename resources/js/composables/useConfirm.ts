import { ref } from 'vue';

/**
 * Options for a single confirmation request.
 */
export type ConfirmOptions = {
    /** Dialog headline, e.g. "Delete story?". */
    title: string;
    /** Optional explanation of what will happen. */
    description?: string;
    /** Label for the confirm button (default "Confirm"). */
    confirmLabel?: string;
    /** Label for the cancel button (default "Cancel"). */
    cancelLabel?: string;
    /** Style the confirm action as destructive (default `true`). */
    destructive?: boolean;
};

type ConfirmState = ConfirmOptions & { open: boolean };

// Module-level singleton: one dialog instance serves every caller, so we never
// stack confirmations and the host can live once in the app shell.
const state = ref<ConfirmState>({ open: false, title: '' });
let resolver: ((value: boolean) => void) | null = null;

/**
 * Resolve the pending confirmation and close the dialog.
 *
 * @param result - `true` when confirmed, `false` when cancelled/dismissed.
 */
function settle(result: boolean): void {
    state.value = { ...state.value, open: false };
    resolver?.(result);
    resolver = null;
}

/**
 * Promise-based confirmation, the standard replacement for `window.confirm`.
 *
 * Renders the global {@link ConfirmDialog} (mounted in the app shell) and
 * resolves to `true` only when the user confirms.
 *
 * @example
 * const { confirm } = useConfirm()
 * if (await confirm({ title: 'Delete story?', confirmLabel: 'Delete' })) {
 *     // proceed
 * }
 *
 * @returns A `confirm(options)` function returning `Promise<boolean>`.
 */
export function useConfirm(): { confirm: (options: ConfirmOptions) => Promise<boolean> } {
    function confirm(options: ConfirmOptions): Promise<boolean> {
        // Reject any in-flight request so a new prompt never leaks a stale resolve.
        resolver?.(false);
        state.value = { ...options, open: true };

        return new Promise<boolean>((resolve) => {
            resolver = resolve;
        });
    }

    return { confirm };
}

/**
 * Internal accessor for the {@link ConfirmDialog} host only.
 *
 * @returns The shared reactive state and the `settle` resolver.
 */
export function useConfirmHost(): {
    state: typeof state;
    settle: (result: boolean) => void;
} {
    return { state, settle };
}
