<template>
  <DashboardLayout>
    <div class="row">
      <div class="col-12">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">SMS Logs</h3>
            <div class="card-tools">
              <div class="input-group input-group-sm">
                <input type="text" v-model="search" class="form-control" placeholder="Search...">
                <div class="input-group-append">
                  <button @click="performSearch" class="btn btn-default">
                    <i class="fas fa-search"></i>
                  </button>
                </div>
              </div>
            </div>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="table table-bordered table-striped">
                <thead>
                  <tr>
                    <th>Title</th>
                    <th>Recipients</th>
                    <th>Message</th>
                    <th>Status</th>
                    <th>Sent At</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody v-if="texts.data.length > 0">
                  <tr v-for="text in texts.data" :key="text.id">
                    <td>{{ text.text_title }}</td>
                    <td>{{ text.contacts_count }}</td>
                    <td>
                      <span class="text-truncate d-inline-block" style="max-width: 200px;">
                        {{ text.message }}
                      </span>
                    </td>
                    <td>
                      <span class="badge" :style="{ backgroundColor: text.status?.color_code || '#777' }">
                        {{ text.status?.text_status_name || 'Unknown' }}
                      </span>
                    </td>
                    <td>{{ formatDate(text.created_at) }}</td>
                    <td>
                      <a :href="route('sms.show', text.id)" class="btn btn-info btn-sm">
                        <i class="fas fa-eye"></i> View Details
                      </a>
                    </td>
                  </tr>
                </tbody>
                <tbody v-else>
                  <tr>
                    <td colspan="6" class="text-center">No SMS logs found</td>
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

// Search functionality
const search = ref('');

const performSearch = () => {
  router.get(route('sms.logs'), {
    search: search.value
  }, {
    preserveState: true,
    replace: true
  });
};

// Format date for display
const formatDate = (dateString) => {
  if (!dateString) return '';
  const date = new Date(dateString);
  return date.toLocaleString();
};
</script>
