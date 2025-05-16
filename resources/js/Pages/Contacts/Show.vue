<template>
  <DashboardLayout>
    <div class="container-fluid">
      <div class="row mb-3">
        <div class="col-12">
          <h2>Contact Details</h2>
          <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
              <li class="breadcrumb-item">
                <Link :href="route('contacts.index')">Contacts</Link>
              </li>
              <li class="breadcrumb-item active">View</li>
            </ol>
          </nav>
        </div>
      </div>

      <div class="row">
        <div class="col-md-4">
          <div class="card mb-4">
            <div class="card-header">
              <h5 class="card-title mb-0">Contact Group Information</h5>
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
            <div class="card-footer">
              <div class="d-flex justify-content-between">
                <Link :href="route('contacts.index')" class="btn btn-secondary">
                  <i class="fas fa-arrow-left me-1"></i> Back
                </Link>
                <Link :href="route('contacts.edit', contact.id)" class="btn btn-primary">
                  <i class="fas fa-edit me-1"></i> Edit
                </Link>
              </div>
            </div>
          </div>
        </div>

        <div class="col-md-8">
          <div class="card">
            <div class="card-header">
              <h5 class="card-title mb-0">Contact List</h5>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                  <thead>
                    <tr>
                      <th style="width: 5%">#</th>
                      <th style="width: 45%">Name</th>
                      <th style="width: 50%">Telephone</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(contact, index) in contactLists" :key="contact.id">
                      <td>{{ index + 1 }}</td>
                      <td>{{ contact.name }}</td>
                      <td>{{ contact.telephone }}</td>
                    </tr>
                    <tr v-if="contactLists.length === 0">
                      <td colspan="3" class="text-center">No contacts found</td>
                    </tr>
                  </tbody>
                </table>
              </div>
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
  return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
};

const getStatusBadgeClass = (isActive) => {
  return isActive 
    ? 'badge bg-success' 
    : 'badge bg-danger';
};
</script>
