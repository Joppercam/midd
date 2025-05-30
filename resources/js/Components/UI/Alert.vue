<template>
  <Transition
    enter-active-class="transition ease-out duration-300"
    enter-from-class="transform opacity-0 scale-95"
    enter-to-class="transform opacity-100 scale-100"
    leave-active-class="transition ease-in duration-200"
    leave-from-class="transform opacity-100 scale-100"
    leave-to-class="transform opacity-0 scale-95"
  >
    <div v-if="show" :class="alertClasses">
      <div class="flex">
        <div class="flex-shrink-0">
          <component :is="iconComponent" :class="iconClasses" aria-hidden="true" />
        </div>
        <div class="ml-3 flex-1">
          <h3 v-if="title" :class="titleClasses">
            {{ title }}
          </h3>
          <div :class="contentClasses">
            <slot />
          </div>
          <div v-if="$slots.actions" class="mt-4">
            <div class="-mx-2 -my-1.5 flex">
              <slot name="actions" />
            </div>
          </div>
        </div>
        <div v-if="dismissible" class="ml-auto pl-3">
          <div class="-mx-1.5 -my-1.5">
            <button
              type="button"
              @click="$emit('dismiss')"
              :class="dismissButtonClasses"
            >
              <span class="sr-only">Cerrar</span>
              <XMarkIcon class="h-5 w-5" aria-hidden="true" />
            </button>
          </div>
        </div>
      </div>
    </div>
  </Transition>
</template>

<script setup>
import { computed } from 'vue'
import {
  CheckCircleIcon,
  ExclamationTriangleIcon,
  InformationCircleIcon,
  XCircleIcon,
  XMarkIcon
} from '@heroicons/vue/24/outline'

const props = defineProps({
  type: {
    type: String,
    default: 'info',
    validator: (value) => ['success', 'warning', 'error', 'info'].includes(value)
  },
  title: String,
  show: {
    type: Boolean,
    default: true
  },
  dismissible: {
    type: Boolean,
    default: false
  }
})

defineEmits(['dismiss'])

const typeConfig = {
  success: {
    bgColor: 'bg-green-50',
    iconColor: 'text-green-400',
    titleColor: 'text-green-800',
    contentColor: 'text-green-700',
    dismissColor: 'bg-green-50 text-green-500 hover:bg-green-100 focus:ring-green-600',
    icon: CheckCircleIcon
  },
  warning: {
    bgColor: 'bg-yellow-50',
    iconColor: 'text-yellow-400',
    titleColor: 'text-yellow-800',
    contentColor: 'text-yellow-700',
    dismissColor: 'bg-yellow-50 text-yellow-500 hover:bg-yellow-100 focus:ring-yellow-600',
    icon: ExclamationTriangleIcon
  },
  error: {
    bgColor: 'bg-red-50',
    iconColor: 'text-red-400',
    titleColor: 'text-red-800',
    contentColor: 'text-red-700',
    dismissColor: 'bg-red-50 text-red-500 hover:bg-red-100 focus:ring-red-600',
    icon: XCircleIcon
  },
  info: {
    bgColor: 'bg-blue-50',
    iconColor: 'text-blue-400',
    titleColor: 'text-blue-800',
    contentColor: 'text-blue-700',
    dismissColor: 'bg-blue-50 text-blue-500 hover:bg-blue-100 focus:ring-blue-600',
    icon: InformationCircleIcon
  }
}

const config = computed(() => typeConfig[props.type])

const alertClasses = computed(() => [
  'rounded-md p-4',
  config.value.bgColor
])

const iconComponent = computed(() => config.value.icon)

const iconClasses = computed(() => [
  'h-5 w-5',
  config.value.iconColor
])

const titleClasses = computed(() => [
  'text-sm font-medium',
  config.value.titleColor
])

const contentClasses = computed(() => [
  'text-sm',
  config.value.contentColor,
  props.title ? 'mt-2' : ''
])

const dismissButtonClasses = computed(() => [
  'inline-flex rounded-md p-1.5 focus:outline-none focus:ring-2 focus:ring-offset-2',
  config.value.dismissColor
])
</script>