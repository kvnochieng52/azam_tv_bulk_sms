<template>
  <div class="progress-donut" :style="containerStyle">
    <svg :width="size" :height="size" viewBox="0 0 42 42">
      <circle 
        class="donut-ring"
        cx="21"
        cy="21"
        r="15.91549430918954"
        fill="transparent"
        :stroke="backgroundColor"
        stroke-width="3"
      />
      <circle
        class="donut-segment"
        cx="21"
        cy="21"
        r="15.91549430918954"
        fill="transparent"
        :stroke="color"
        stroke-width="3"
        :stroke-dasharray="`${percentage} ${100 - percentage}`"
        stroke-dashoffset="25"
      />
      <text 
        v-if="showPercentage && showCount"
        class="donut-number" 
        x="50%" 
        y="45%" 
        text-anchor="middle" 
        alignment-baseline="middle"
        :style="{ fontSize: textSize + 'px', fill: percentage === 100 ? '#28a745' : textColor }"
      >
        {{ processed }}/{{ total }}
      </text>
      <text 
        v-else-if="showPercentage"
        class="donut-number" 
        x="50%" 
        y="50%" 
        text-anchor="middle" 
        alignment-baseline="middle"
        :style="{ fontSize: textSize + 'px', fill: percentage === 100 ? '#28a745' : textColor }"
      >
        {{ percentage }}%
      </text>
    </svg>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  percentage: {
    type: Number,
    default: 0,
    validator: (value) => value >= 0 && value <= 100
  },
  processed: {
    type: Number,
    default: 0
  },
  total: {
    type: Number,
    default: 0
  },
  size: {
    type: Number,
    default: 40
  },
  color: {
    type: String,
    default: '#28a745' // success green
  },
  backgroundColor: {
    type: String,
    default: '#e9ecef' // light gray
  },
  textColor: {
    type: String,
    default: '#343a40' // dark gray
  },
  showPercentage: {
    type: Boolean,
    default: true
  },
  showCount: {
    type: Boolean,
    default: false
  }
});

const containerStyle = computed(() => ({
  width: `${props.size}px`,
  height: `${props.size}px`,
}));

const textSize = computed(() => props.size / 3.5);
</script>

<style scoped>
.progress-donut {
  display: inline-block;
  position: relative;
}

.donut-ring {
  stroke-linecap: round;
}

.donut-segment {
  transform: rotate(-90deg);
  transform-origin: center;
  stroke-linecap: round;
  transition: stroke-dasharray 0.3s ease;
}

.donut-number {
  font-family: Arial, sans-serif;
  font-weight: bold;
}
</style>
