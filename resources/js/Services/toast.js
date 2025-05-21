import { useToast } from 'vue-toastification';

// Create a reusable toast service that can be imported anywhere
const toast = useToast();

export default {
  success(message) {
    toast.success(message, {
      timeout: 5000
    });
  },
  error(message) {
    toast.error(message, {
      timeout: 7000
    });
  },
  warning(message) {
    toast.warning(message, {
      timeout: 5000
    });
  },
  info(message) {
    toast.info(message, {
      timeout: 5000
    });
  }
};
