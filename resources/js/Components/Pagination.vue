<template>
    <nav v-if="links.length > 3" class="flex items-center justify-between">
        <div class="flex justify-between flex-1 sm:hidden">
            <Link
                v-if="links[0].url"
                :href="links[0].url"
                class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:text-gray-400"
            >
                Anterior
            </Link>
            <Link
                v-if="links[links.length - 1].url"
                :href="links[links.length - 1].url"
                class="relative ml-3 inline-flex items-center px-4 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:text-gray-400"
            >
                Siguiente
            </Link>
        </div>
        <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
            <div class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                <Link
                    v-for="(link, index) in links"
                    :key="index"
                    :href="link.url"
                    :class="[
                        'relative inline-flex items-center px-2 py-2 text-sm font-medium border',
                        link.active
                            ? 'z-10 bg-indigo-50 border-indigo-500 text-indigo-600'
                            : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50',
                        index === 0 ? 'rounded-l-md' : '',
                        index === links.length - 1 ? 'rounded-r-md' : '',
                        !link.url ? 'cursor-not-allowed opacity-50' : 'hover:text-gray-400'
                    ]"
                    :disabled="!link.url"
                    v-html="link.label"
                />
            </div>
        </div>
    </nav>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    links: {
        type: Array,
        required: true,
    },
});
</script>