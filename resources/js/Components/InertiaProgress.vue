<template>
  <div 
    v-show="show"
    class="inertia-progress-bar"
    :style="{
      width: `${progress}%`,
      backgroundColor: color,
      opacity: opacity,
      transition: transitionStyle,
    }"
  />
</template>

<script setup>
import { onMounted, onUnmounted, ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
  color: {
    type: String,
    default: '#007bff'
  },
  height: {
    type: Number,
    default: 3
  }
});

const progress = ref(0);
const show = ref(false);
const opacity = ref(0.8);
const transitionStyle = computed(() => show.value ? 'width 0.3s ease' : 'all 0.3s ease');

let timeout;

function startProgress() {
  progress.value = 0;
  show.value = true;
  opacity.value = 0.8;
  incrementProgress();
}

function incrementProgress() {
  if (progress.value < 100) {
    const targetProgress = Math.min(progress.value + (100 - progress.value) / 10, 95);
    progress.value = targetProgress;
    timeout = setTimeout(incrementProgress, 300);
  }
}

function completeProgress() {
  clearTimeout(timeout);
  progress.value = 100;
  
  // Fade out
  setTimeout(() => {
    opacity.value = 0;
    setTimeout(() => {
      show.value = false;
      progress.value = 0;
    }, 300);
  }, 500);
}

onMounted(() => {
  router.on('start', startProgress);
  router.on('finish', completeProgress);
});

onUnmounted(() => {
  router.off('start', startProgress);
  router.off('finish', completeProgress);
  clearTimeout(timeout);
});
</script>

<style>
.inertia-progress-bar {
  position: fixed;
  top: 0;
  left: 0;
  height: 3px;
  z-index: 9999;
}
</style>
