<template>
  <DashboardLayout>
    <div class="row mb-3">
      <div class="col-12">
        <div class="card">
          <div class="card-header bg-primary text-white">
            <div class="d-flex justify-content-between align-items-center">
              <h3 class="card-title"><i class="fas fa-address-card me-2"></i>Contact Details</h3>
              <Link :href="route('contacts.index')" class="btn btn-outline-light btn-sm">
                <i class="fas fa-arrow-left me-1"></i> Back to Contacts
              </Link>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-4">
        <div class="card custom-card mb-4">
          <div class="card-header bg-info text-white">
            <h5 class="card-title mb-0"><i class="fas fa-info-circle me-2"></i>Contact Group Information</h5>
          </div>
          <div class="card-body">
            <table class="table">
              <tbody>
                <tr>
                  <th style="width: 35%">Title:</th>
                  <td>{{ contact.title }}</td>
                </tr>
                <tr>
                  <th>Status:</th>
                  <td>
                    <span :class="getStatusBadgeClass(contact.is_active)">
                      {{ contact.is_active ? 'Active' : 'Inactive' }}
                    </span>
                  </td>
                </tr>
                <tr>
                  <th>Total Contacts:</th>
                  <td>{{ contactLists.length }}</td>
                </tr>
                <tr>
                  <th>Created At:</th>
                  <td>{{ formatDate(contact.created_at) }}</td>
                </tr>
                <tr>
                  <th>Updated At:</th>
                  <td>{{ formatDate(contact.updated_at) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="card-footer bg-light">
            <div class="d-flex justify-content-between">
              <Link :href="route('contacts.index')" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i> Back
              </Link>
              <Link :href="route('contacts.edit', contact.id)" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-edit me-1"></i> Edit
              </Link>
            </div>
          </div>
        </div>
      </div>

      <div class="col-md-8">
        <div class="card custom-card">
          <div class="card-header bg-success text-white">
            <h5 class="card-title mb-0"><i class="fas fa-users me-2"></i>Contact List</h5>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-striped table-hover mb-0">
                <thead class="bg-light">
                  <tr>
                    <th style="width: 5%" class="ps-3">#</th>
                    <th style="width: 45%">
                      <i class="fas fa-user me-1"></i> Name
                    </th>
                    <th style="width: 50%">
                      <i class="fas fa-phone me-1"></i> Telephone
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(contact, index) in contactLists" :key="contact.id">
                    <td class="ps-3">{{ index + 1 }}</td>
                    <td class="fw-medium">{{ contact.name }}</td>
                    <td>
                      <span class="text-primary">
                        <i class="fas fa-phone-alt me-1 small"></i>
                        {{ contact.telephone }}
                      </span>
                    </td>
                  </tr>
                  <tr v-if="contactLists.length === 0">
                    <td colspan="3" class="text-center py-4">
                      <div class="text-muted">
                        <i class="fas fa-folder-open fa-2x mb-2"></i>
                        <p>No contacts found</p>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';

const props = defineProps({
  contact: Object,
  contactLists: Array
});

const formatDate = (dateString) => {
  if (!dateString) return '';
  const date = new Date(dateString);
  // Return a more compact date format
  return new Intl.DateTimeFormat('en-US', { 
    year: 'numeric',
    month: 'short',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    hour12: true
  }).format(date);
};

const getStatusBadgeClass = (isActive) => {
  return isActive 
    ? 'badge text-bg-success px-2 py-1' 
    : 'badge text-bg-danger px-2 py-1';
};
</script>

<style scoped>
/* Card styling */
.card {
  border-radius: 0.5rem;
  box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
  border: none;
  overflow: hidden;
  margin-bottom: 1.5rem;
}

.card-header {
  border-bottom: 1px solid rgba(0, 0, 0, 0.05);
  padding: 1.25rem 1.5rem;
}

.card-header .card-title {
  font-weight: 600;
  margin-bottom: 0;
  text-shadow: 0 1px 1px rgba(0, 0, 0, 0.2);
}

/* Custom nested cards */
.custom-card {
  border-radius: 0.5rem;
  box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.1);
  border: none;
  overflow: hidden;
  transition: transform 0.3s, box-shadow 0.3s;
}

.custom-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 0.5rem 0.75rem rgba(0, 0, 0, 0.15);
}

/* Table styling */
.table {
  margin-bottom: 0;
}

.table th {
  font-weight: 600;
  font-size: 0.85rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  border-bottom-width: 1px;
  background-color: #f8f9fa;
  padding: 0.85rem 0.5rem;
  color: #495057;
}

.table td {
  vertical-align: middle;
  padding: 1rem 0.5rem;
  border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

.fw-medium {
  font-weight: 500;
}

/* Badge styling */
.badge {
  font-weight: 500;
  font-size: 0.75rem;
  border-radius: 30px;
  padding: 0.4em 0.85em;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

/* Button styling */
.btn-outline-danger:hover,
.btn-outline-secondary:hover,
.btn-outline-light:hover,
.btn-outline-primary:hover {
  transform: translateY(-1px);
  transition: all 0.2s;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}
</style>
