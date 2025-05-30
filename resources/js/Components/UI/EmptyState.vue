<template>
  <div class="text-center py-12">
    <svg
      v-if="icon"
      class="mx-auto h-12 w-12 text-gray-400"
      fill="none"
      stroke="currentColor"
      viewBox="0 0 24 24"
    >
      <path
        stroke-linecap="round"
        stroke-linejoin="round"
        stroke-width="2"
        :d="icon"
      />
    </svg>
    <component
      v-else-if="iconComponent"
      :is="iconComponent"
      class="mx-auto h-12 w-12 text-gray-400"
    />
    <div v-else class="mx-auto h-12 w-12 text-gray-400">
      <slot name="icon" />
    </div>
    
    <h3 class="mt-2 text-sm font-medium text-gray-900">{{ title }}</h3>
    <p v-if="description" class="mt-1 text-sm text-gray-500">{{ description }}</p>
    
    <div v-if="$slots.action" class="mt-6">
      <slot name="action" />
    </div>
  </div>
</template>

<script setup>
defineProps({
  title: {
    type: String,
    required: true
  },
  description: String,
  icon: String,
  iconComponent: Object
})