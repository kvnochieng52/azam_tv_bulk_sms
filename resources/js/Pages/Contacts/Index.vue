<template>
  <DashboardLayout>
    <div class="container-fluid">
      <div class="row mb-3">
        <div class="col-12 d-flex justify-content-between align-items-center">
          <h2>Contact Management</h2>
          <Link :href="route('contacts.create')" class="btn btn-primary">
            <i class="fas fa-plus me-1"></i> Add New Contact
          </Link>
        </div>
      </div>

      <div class="card">
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-striped table-hover">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Title</th>
                  <th>Contacts Count</th>
                  <th>Status</th>
                  <th>Created At</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(contact, index) in contacts.data" :key="contact.id">
                  <td>{{ index + 1 }}</td>
                  <td>{{ contact.title }}</td>
                  <td>{{ contact.contact_lists_count }}</td>
                  <td>
                    <span :class="getStatusBadgeClass(contact.is_active)">
                      {{ contact.is_active ? 'Active' : 'Inactive' }}
                    </span>
                  </td>
                  <td>{{ formatDate(contact.created_at) }}</td>
                  <td>
                    <div class="btn-group">
                      <Link :href="route('contacts.show', contact.id)" class="btn btn-sm btn-info me-1">
                        <i class="fas fa-eye"></i>
                      </Link>
                      <Link :href="route('contacts.edit', contact.id)" class="btn btn-sm btn-primary me-1">
                        <i class="fas fa-edit"></i>
                      </Link>
                      <Link :href="route('contacts.destroy', contact.id)" method="delete" as="button" class="btn btn-sm btn-danger" @click.prevent="confirmDelete(contact)">
                        <i class="fas fa-trash"></i>
                      </Link>
                    </div>
                  </td>
                </tr>
                <tr v-if="contacts.data.length === 0">
                  <td colspan="6" class="text-center">No contacts found</td>
                </tr>
              </tbody>
            </table>
          </div>
          
          <!-- Pagination -->
          <Pagination :links="contacts.links" />
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
  contacts: Object
});

const formatDate = (dateString) => {
  if (!dateString) return '';
  const date = new Date(dateString);
  return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
};

const getStatusBadgeClass = (isActive) => {
  return isActive 
    ? 'badge bg-success' 
    : 'badge bg-danger';
};

const confirmDelete = (contact) => {
  return confirm(`Are you sure you want to delete "${contact.title}"?`);
};
</script>
