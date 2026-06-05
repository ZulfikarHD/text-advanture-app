<script setup lang="ts">
/**
 * ConfirmDialog - global host for `useConfirm()`.
 *
 * Mounted once in the app shell. Renders a single AlertDialog driven by the
 * shared confirm state so any component can request a confirmation with
 * `await confirm({ ... })` instead of a native browser dialog. The confirm
 * action is styled destructive by default with a clear Cancel.
 */
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { buttonVariants } from '@/components/ui/button';
import { useConfirmHost } from '@/composables/useConfirm';
import { cn } from '@/lib/utils';

const { state, settle } = useConfirmHost();

function onOpenChange(open: boolean): void {
    // Treat any dismissal (overlay click, Escape) as a cancel.
    if (!open) {
        settle(false);
    }
}
</script>

<template>
    <AlertDialog :open="state.open" @update:open="onOpenChange">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>{{ state.title }}</AlertDialogTitle>
                <AlertDialogDescription v-if="state.description">
                    {{ state.description }}
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel @click="settle(false)">
                    {{ state.cancelLabel ?? 'Cancel' }}
                </AlertDialogCancel>
                <AlertDialogAction
                    :class="
                        state.destructive !== false
                            ? cn(buttonVariants({ variant: 'destructive' }))
                            : undefined
                    "
                    @click="settle(true)"
                >
                    {{ state.confirmLabel ?? 'Confirm' }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
