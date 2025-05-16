<template>
  <div class="multiselect-container">
    <div 
      class="form-select select2-container" 
      :class="{ 'is-invalid': error }"
      @click="toggleDropdown"
    >
      <div class="selected-options">
        <div v-if="modelValue.length === 0" class="placeholder text-muted">
          {{ placeholder }}
        </div>
        <div v-else class="selected-tags">
          <span 
            v-for="option in selectedOptions" 
            :key="option.id" 
            class="selected-tag"
          >
            {{ option.label }}
            <button type="button" class="btn-close ms-1" @click.stop="removeOption(option.id)"></button>
          </span>
        </div>
      </div>
      <div class="dropdown-icon">
        <i class="fas" :class="isOpen ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
      </div>
    </div>
    
    <div v-if="error" class="invalid-feedback">{{ error }}</div>
    
    <div v-show="isOpen" class="dropdown-menu show w-100">
      <div class="px-2 py-1">
        <input
          type="text"
          class="form-control form-control-sm"
          placeholder="Search..."
          v-model="searchQuery"
          @click.stop
        />
      </div>
      <div class="dropdown-divider"></div>
      <div class="options-container">
        <button
          v-for="option in filteredOptions"
          :key="option.id"
          type="button"
          class="dropdown-item"
          :class="{ 'active': isSelected(option.id) }"
          @click.stop="toggleOption(option.id)"
        >
          <i class="fas" :class="isSelected(option.id) ? 'fa-check-square' : 'fa-square'"></i>
          <span class="ms-2">{{ option.label }}</span>
          <small v-if="option.count !== undefined" class="text-muted ms-1">({{ option.count }} contacts)</small>
        </button>
        <div v-if="filteredOptions.length === 0" class="dropdown-item text-muted">
          No matching options found
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
  options: {
    type: Array,
    required: true
  },
  modelValue: {
    type: Array,
    default: () => []
  },
  placeholder: {
    type: String,
    default: 'Select options'
  },
  error: {
    type: String,
    default: ''
  }
});

const emit = defineEmits(['update:modelValue']);

const isOpen = ref(false);
const searchQuery = ref('');

// Click outside handler
const handleClickOutside = (event) => {
  const container = document.querySelector('.multiselect-container');
  if (container && !container.contains(event.target)) {
    isOpen.value = false;
  }
};

// Selected options
const selectedOptions = computed(() => {
  return props.options.filter(option => props.modelValue.includes(option.id));
});

// Filtered options based on search
const filteredOptions = computed(() => {
  if (!searchQuery.value) return props.options;
  
  return props.options.filter(option => 
    option.label.toLowerCase().includes(searchQuery.value.toLowerCase())
  );
});

// Check if an option is selected
const isSelected = (id) => {
  return props.modelValue.includes(id);
};

// Toggle dropdown
const toggleDropdown = () => {
  isOpen.value = !isOpen.value;
};

// Toggle option selection
const toggleOption = (id) => {
  const newValue = [...props.modelValue];
  const index = newValue.indexOf(id);
  
  if (index === -1) {
    newValue.push(id);
  } else {
    newValue.splice(index, 1);
  }
  
  emit('update:modelValue', newValue);
};

// Remove a selected option
const removeOption = (id) => {
  const newValue = props.modelValue.filter(item => item !== id);
  emit('update:modelValue', newValue);
};

// Add/remove document event listeners
onMounted(() => {
  document.addEventListener('click', handleClickOutside);
});

onBeforeUnmount(() => {
  document.removeEventListener('click', handleClickOutside);
});
</script>

<style scoped>
.multiselect-container {
  position: relative;
}

.select2-container {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.375rem 0.75rem;
  min-height: 38px;
  cursor: pointer;
  border: 1px solid #d1d5db;
  border-radius: 4px;
}

.selected-options {
  flex: 1;
  overflow: hidden;
}

.selected-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 0.25rem;
}

.selected-tag {
  display: inline-flex;
  align-items: center;
  background-color: #e9ecef;
  border-radius: 0.25rem;
  padding: 0.125rem 0.5rem;
  font-size: 0.875rem;
}

.dropdown-menu {
  max-height: 250px;
  overflow-y: auto;
}

.options-container {
  max-height: 180px;
  overflow-y: auto;
}

.dropdown-item {
  display: flex;
  align-items: center;
  padding: 0.5rem 1rem;
}

.dropdown-item.active {
  background-color: #e9ecef;
  color: #212529;
}
</style>
