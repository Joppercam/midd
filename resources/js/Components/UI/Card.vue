<template>
  <div :class="[
    'bg-white overflow-hidden shadow-sm rounded-lg',
    noPadding ? '' : 'p-6',
    className
  ]">
    <div v-if="$slots.header || title" class="px-6 py-4 border-b border-gray-200">
      <slot name="header">
        <div class="flex items-center justify-between">
          <h3 class="text-lg font-medium text-gray-900">{{ title }}</h3>
          <slot name="actions" />
        </div>
      </slot>
    </div>
    
    <div :class="[!noPadding && ($slots.header || title) ? 'p-6' : '']">
      <slot />
    </div>
    
    <div v-if="$slots.footer" class="px-6 py-4 bg-gray-50 border-t border-gray-200">
      <slot name="footer" />
    </div>
  </div>
</template>

<script setup>
defineProps({
  title: String,
  noPadding: {
    type: Boolean,
    default: false
  },
  className: {
    type: String,
    default: ''
  }
})
</script>