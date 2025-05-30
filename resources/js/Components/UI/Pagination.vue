<template>
  <nav class="flex items-center justify-between px-4 sm:px-0">
    <div class="-mt-px flex w-0 flex-1">
      <Link
        v-if="prevUrl"
        :href="prevUrl"
        class="inline-flex items-center border-t-2 border-transparent pr-1 pt-4 text-sm font-medium text-gray-500 hover:border-gray-300 hover:text-gray-700"
      >
        <ArrowLongLeftIcon class="mr-3 h-5 w-5 text-gray-400" aria-hidden="true" />
        Anterior
      </Link>
    </div>
    <div class="hidden md:-mt-px md:flex">
      <template v-for="link in visibleLinks" :key="link.label">
        <span
          v-if="link.label === '...'"
          class="inline-flex items-center border-t-2 border-transparent px-4 pt-4 text-sm font-medium text-gray-500"
        >
          ...
        </span>
        <Link
          v-else-if="link.url"
          :href="link.url"
          :class="[
            'inline-flex items-center border-t-2 px-4 pt-4 text-sm font-medium',
            link.active
              ? 'border-indigo-500 text-indigo-600'
              : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'
          ]"
        >
          {{ link.label }}
        </Link>
        <span
          v-else
          :class="[
            'inline-flex items-center border-t-2 px-4 pt-4 text-sm font-medium',
            link.active
              ? 'border-indigo-500 text-indigo-600'
              : 'border-transparent text-gray-500'
          ]"
        >
          {{ link.label }}
        </span>
      </template>
    </div>
    <div class="-mt-px flex w-0 flex-1 justify-end">
      <Link
        v-if="nextUrl"
        :href="nextUrl"
        class="inline-flex items-center border-t-2 border-transparent pl-1 pt-4 text-sm font-medium text-gray-500 hover:border-gray-300 hover:text-gray-700"
      >
        Siguiente
        <ArrowLongRightIcon class="ml-3 h-5 w-5 text-gray-400" aria-hidden="true" />
      </Link>
    </div>
  </nav>
</template>

<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { ArrowLongLeftIcon, ArrowLongRightIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  links: {
    type: Array,
    required: true
  }
})

const prevUrl = computed(() => {
  const prevLink = props.links.find(link => link.label.includes('Previous'))
  return prevLink?.url
})

const nextUrl = computed(() => {
  const nextLink = props.links.find(link => link.label.includes('Next'))
  return nextLink?.url
})

const visibleLinks = computed(() => {
  return props.links.filter(link => 
    !link.label.includes('Previous') && !link.label.includes('Next')
  )
})
</script>