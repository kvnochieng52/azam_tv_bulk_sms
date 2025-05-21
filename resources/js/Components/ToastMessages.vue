<template>
  <!-- Toast messages component - invisible container to satisfy template requirements -->
  <div style="display: none;"></div>
</template>

<script setup>
import { onMounted, watch } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import { useToast } from 'vue-toastification';

// Access the toast API
const toast = useToast();
const page = usePage();

// Function to display toast messages based on flash data
const displayToastMessages = () => {
  // Make sure flash exists before trying to access properties
  const flash = page.props.flash || {};
  
  // Success message
  if (flash.success) {
    toast.success(flash.success, {
      timeout: 5000
    });
  }
  
  // Error message
  if (flash.error) {
    toast.error(flash.error, {
      timeout: 7000
    });
  }
  
  // Warning message
  if (flash.warning) {
    toast.warning(flash.warning, {
      timeout: 5000
    });
  }
  
  // Info message
  if (flash.info) {
    toast.info(flash.info, {
      timeout: 5000
    });
  }
};

// Watch for changes in flash messages
watch(() => page.props, (newProps) => {
  // Only call displayToastMessages if flash property exists and has changed
  if (newProps && newProps.flash) {
    displayToastMessages();
  }
}, { deep: true });

// Display messages on initial load and after Inertia navigation events
onMounted(() => {
  displayToastMessages();
  
  // Listen for the finish event, which fires after a successful Inertia visit
  router.on('finish', () => {
    // Small delay to ensure page props are updated
    setTimeout(() => {
      displayToastMessages();
    }, 100);
  });
});
</script>
