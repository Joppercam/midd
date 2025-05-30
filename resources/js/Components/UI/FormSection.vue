<template>
  <div class="md:grid md:grid-cols-3 md:gap-6">
    <div class="md:col-span-1">
      <div class="px-4 sm:px-0">
        <h3 class="text-lg font-medium leading-6 text-gray-900">{{ title }}</h3>
        <p class="mt-1 text-sm text-gray-600">
          {{ description }}
        </p>
        <div v-if="$slots.aside" class="mt-4">
          <slot name="aside" />
        </div>
      </div>
    </div>
    <div class="mt-5 md:col-span-2 md:mt-0">
      <form @submit.prevent="$emit('submit')">
        <div class="shadow sm:overflow-hidden sm:rounded-md">
          <div class="space-y-6 bg-white px-4 py-5 sm:p-6">
            <slot />
          </div>
          <div v-if="$slots.actions" class="bg-gray-50 px-4 py-3 text-right sm:px-6">
            <slot name="actions" />
          </div>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
defineProps({
  title: {
    type: String,
    required: true
  },
  description: String
})

defineEmits(['submit'])
</script>