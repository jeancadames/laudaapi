<script setup lang="ts">

</script>

<template>
    <div class="min-h-screen bg-background text-foreground">
        <header class="border-b bg-card/50 backdrop-blur">
            <div class="mx-auto max-w-4xl px-6 py-6">
                <slot name="header" />
            </div>
        </header>

        <main class="mx-auto max-w-4xl px-6 py-10 prose prose-neutral dark:prose-invert prose-headings:font-semibold prose-headings:tracking-tight prose-p:text-muted-foreground prose-li:marker:text-muted-foreground">
            <slot />
        </main>

        <footer class="border-t py-6 text-center text-xs text-muted-foreground">
            © {{ new Date().getFullYear() }} LaudaAPI — Todos los derechos reservados
        </footer>
    </div>
</template>
