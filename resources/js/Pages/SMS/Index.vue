<template>
  <DashboardLayout>
    <div class="row">
      <div class="col-12">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">SMS Messages</h3>
            <div class="card-tools">
              <a :href="route('sms.create')" class="btn btn-primary btn-sm">
                <i class="fas fa-plus mr-1"></i> New SMS
              </a>
            </div>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="table table-bordered table-striped">
                <thead>
                  <tr>
                    <th>Title</th>
                    <th>Recipients</th>
                    <th>Scheduled</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody v-if="texts.data.length > 0">
                  <tr v-for="text in texts.data" :key="text.id">
                    <td>{{ text.text_title }}</td>
                    <td>{{ text.contacts_count }}</td>
                    <td>
                      <span v-if="text.scheduled" class="badge bg-info">
                        <i class="fas fa-clock mr-1"></i>
                        {{ formatDate(text.schedule_date) }}
                      </span>
                      <span v-else class="badge bg-success">Immediate</span>
                    </td>
                    <td>
                      <span class="badge" :style="{ backgroundColor: text.status?.color_code || '#777' }">
                        {{ text.status?.text_status_name || 'Unknown' }}
                      </span>
                    </td>
                    <td>{{ formatDate(text.created_at) }}</td>
                    <td>
                      <div class="btn-group">
                        <a :href="route('sms.show', text.id)" class="btn btn-info btn-sm">
                          <i class="fas fa-eye"></i>
                        </a>
                        <a v-if="text.status_id <= 1" :href="route('sms.edit', text.id)" class="btn btn-primary btn-sm">
                          <i class="fas fa-edit"></i>
                        </a>
                        <button 
                          v-if="text.status_id <= 2" 
                          @click="confirmDelete(text)" 
                          class="btn btn-danger btn-sm"
                        >
                          <i class="fas fa-trash"></i>
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
                <tbody v-else>
                  <tr>
                    <td colspan="6" class="text-center">No SMS messages found</td>
                  </tr>
                </tbody>
              </table>
            </div>
            
            <!-- Pagination -->
            <div class="mt-4" v-if="texts.data.length > 0">
              <pagination :links="texts.links" />
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Confirm Delete</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <p>Are you sure you want to delete the SMS <strong>"{{ textToDelete?.text_title }}"</strong>?</p>
            <p class="text-danger">This action cannot be undone.</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-danger" @click="deleteText" :disabled="isDeleting">
              <i class="fas fa-trash mr-1"></i> Delete
            </button>
          </div>
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>

<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Pagination from '@/Components/Pagination.vue';

// Props from controller
const props = defineProps({
  texts: Object
});

// Delete functionality
const textToDelete = ref(null);
const isDeleting = ref(false);

const confirmDelete = (text) => {
  textToDelete.value = text;
  // Show modal
  const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
  modal.show();
};

const deleteText = () => {
  if (textToDelete.value) {
    isDeleting.value = true;
    router.delete(route('sms.destroy', textToDelete.value.id), {
      onSuccess: () => {
        // Close modal
        bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
        isDeleting.value = false;
        textToDelete.value = null;
      },
      onError: () => {
        isDeleting.value = false;
      }
    });
  }
};

// Format date for display
const formatDate = (dateString) => {
  if (!dateString) return '';
  const date = new Date(dateString);
  return date.toLocaleString();
};
</script>
