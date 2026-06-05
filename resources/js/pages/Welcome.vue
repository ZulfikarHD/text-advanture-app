<script setup lang="ts">
/**
 * Welcome - public landing page.
 *
 * The only unauthenticated surface besides the auth screens. Token-only styling
 * with full light/dark parity and a single primary call-to-action (Hick's Law)
 * that routes the visitor into the app - the Workspace when already signed in,
 * otherwise sign-in.
 */
import { Head, Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';
</script>

<template>
    <Head title="Welcome" />

    <div class="flex min-h-screen flex-col bg-background text-foreground">
        <!-- Top bar -->
        <header
            class="mx-auto flex w-full max-w-5xl items-center justify-between gap-4 p-6"
        >
            <div class="flex items-center gap-2">
                <span
                    class="flex size-9 items-center justify-center rounded-xl bg-primary text-primary-foreground"
                >
                    <AppLogoIcon class="size-5" />
                </span>
                <span class="text-base font-semibold tracking-tight">DINE</span>
            </div>

            <nav class="flex items-center gap-2">
                <Button v-if="$page.props.auth.user" as-child class="h-11">
                    <Link :href="dashboard()">Go to workspace</Link>
                </Button>
                <template v-else>
                    <Button as-child variant="ghost" class="h-11">
                        <Link :href="login()">Log in</Link>
                    </Button>
                    <Button
                        v-if="$page.props.canRegister"
                        as-child
                        variant="outline"
                        class="h-11"
                    >
                        <Link :href="register()">Create account</Link>
                    </Button>
                </template>
            </nav>
        </header>

        <!-- Hero -->
        <main
            class="mx-auto flex w-full max-w-5xl flex-1 flex-col items-center justify-center gap-8 px-6 py-16 text-center"
        >
            <span
                class="flex size-16 items-center justify-center rounded-2xl bg-primary text-primary-foreground shadow-sm"
            >
                <AppLogoIcon class="size-8" />
            </span>

            <div class="space-y-4">
                <h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">
                    Directed Interactive Novel Engine
                </h1>
                <p
                    class="mx-auto max-w-prose text-base leading-relaxed text-muted-foreground"
                >
                    Author living, character-driven stories and play them as an
                    evolving interactive novel. Sign in to your authoring
                    workspace to get started.
                </p>
            </div>

            <!-- Single primary action (Hick's Law) -->
            <Button v-if="$page.props.auth.user" as-child class="h-11 px-6">
                <Link :href="dashboard()">Open workspace</Link>
            </Button>
            <Button v-else as-child class="h-11 px-6">
                <Link :href="login()">Log in to start</Link>
            </Button>
        </main>

        <footer
            class="mx-auto w-full max-w-5xl p-6 text-center text-xs text-muted-foreground"
        >
            Directed Interactive Novel Engine
        </footer>
    </div>
</template>
