<template>
  <DashboardLayout>
    <div class="row mb-3">
      <div class="col-12">
        <div class="card">
          <div class="card-header bg-primary text-white">
            <div class="d-flex justify-content-between align-items-center">
              <h3 class="card-title">
                <i class="fas fa-address-book me-2"></i>Contact Groups
              </h3>
              <div class="d-flex align-items-center">
                <!-- Search Input -->
                <div class="input-group me-2" style="width: 250px">
                  <input
                    type="text"
                    v-model="search"
                    class="form-control"
                    placeholder="Search contacts..."
                    @input="performSearch"
                  />
                  <span class="input-group-text bg-primary text-white">
                    <i class="fas fa-search"></i>
                  </span>
                </div>
                <!-- Add Contact Button -->
                <Link
                  :href="route('contacts.create')"
                  class="btn btn-primary btn-sm"
                >
                  <i class="fas fa-plus me-1"></i> Add Contact
                </Link>
              </div>
            </div>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-striped table-hover m-0">
                <thead class="bg-light">
                  <tr>
                    <th class="ps-3" width="5%">#</th>
                    <th width="35%">Title</th>
                    <th width="15%">
                      <i class="fas fa-address-book me-1"></i> Contacts Count
                    </th>
                    <th width="15%">
                      <i class="fas fa-toggle-on me-1"></i> Status
                    </th>
                    <th width="15%">
                      <i class="fas fa-clock me-1"></i> Created
                    </th>
                    <th width="15%" class="text-center">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="(contact, index) in contacts.data"
                    :key="contact.id"
                    :class="{ 'active-contact': contact.is_active }"
                  >
                    <td class="ps-3">{{ index + 1 }}</td>
                    <td>
                      <div class="d-flex align-items-center">
                        <div class="contact-name">
                          <span class="fw-medium">{{ contact.title }}</span>
                          <small
                            v-if="contact.is_active"
                            class="text-success d-block"
                            ><i class="fas fa-check-circle me-1"></i
                            >Active</small
                          >
                          <small v-else class="text-danger d-block"
                            ><i class="fas fa-times-circle me-1"></i
                            >Inactive</small
                          >
                        </div>
                      </div>
                    </td>
                    <td>
                      <span class="badge bg-info">
                        {{ contact.contact_lists_count }}
                      </span>
                    </td>
                    <td>
                      <span :class="getStatusBadgeClass(contact.is_active)">
                        {{ contact.is_active ? "Active" : "Inactive" }}
                      </span>
                    </td>
                    <td>{{ formatDate(contact.created_at) }}</td>
                    <td>
                      <div class="d-flex justify-content-center">
                        <Link
                          :href="route('contacts.show', contact.id)"
                          class="btn btn-outline-info btn-sm me-1"
                          title="View Contact"
                        >
                          <i class="fas fa-eye"></i>
                        </Link>
                        <Link
                          :href="route('contacts.edit', contact.id)"
                          class="btn btn-outline-primary btn-sm me-1"
                          title="Edit Contact"
                        >
                          <i class="fas fa-edit"></i>
                        </Link>
                        <Link
                          :href="route('contacts.destroy', contact.id)"
                          method="delete"
                          as="button"
                          class="btn btn-outline-danger btn-sm"
                          @click.prevent="confirmDelete(contact)"
                          title="Delete Contact"
                        >
                          <i class="fas fa-trash"></i>
                        </Link>
                      </div>
                    </td>
                  </tr>
                  <tr v-if="contacts.data.length === 0">
                    <td colspan="6" class="text-center py-4">
                      <div class="text-muted">
                        <i class="fas fa-folder-open fa-2x mb-2"></i>
                        <p>No contacts found</p>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Pagination with more compact styling -->
            <div class="p-3">
              <Pagination :links="contacts.links" />
            </div>
          </div>
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { Link, router } from "@inertiajs/vue3";
import DashboardLayout from "@/Layouts/DashboardLayout.vue";
import Pagination from "@/Components/Pagination.vue";

const props = defineProps({
  contacts: Object,
});

// Search functionality
const search = ref("");
let searchTimeout = null;

const performSearch = () => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    router.get(
      route("contacts.index"),
      { search: search.value },
      { preserveState: true, preserveScroll: true }
    );
  }, 350); // Debounce search input
};

const formatDate = (dateString) => {
  if (!dateString) return "";
  const date = new Date(dateString);
  // Return a more compact date format
  return new Intl.DateTimeFormat("en-US", {
    year: "numeric",
    month: "short",
    day: "2-digit",
    hour: "2-digit",
    minute: "2-digit",
    hour12: true,
  }).format(date);
};

const getStatusBadgeClass = (isActive) => {
  return isActive
    ? "badge text-bg-success px-2 py-1"
    : "badge text-bg-danger px-2 py-1";
};

const confirmDelete = (contact) => {
  return confirm(
    `Are you sure you want to delete "${contact.title}"? This will also delete all contacts in this group.`
  );
};
</script>

<style scoped>
/* Add custom styling for contacts page */
.card {
  border-radius: 0.5rem;
  box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
  border: none;
  overflow: hidden;
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

/* Active contact row styling */
.active-contact {
  background-color: rgba(25, 135, 84, 0.07) !important;
  border-left: 4px solid #198754;
}

.contact-name {
  line-height: 1.4;
}

.contact-name small {
  font-size: 0.75rem;
  opacity: 0.8;
}

.contact-name small i {
  font-size: 0.7rem;
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

/* Button hover effects */
.btn-outline-info:hover,
.btn-outline-primary:hover,
.btn-outline-danger:hover {
  transform: translateY(-1px);
  transition: all 0.2s;
}

/* Pagination styling */
:deep(.pagination) {
  margin-bottom: 0;
}

:deep(.page-item .page-link) {
  border-radius: 0.25rem;
  margin: 0 0.15rem;
}

:deep(.page-item.active .page-link) {
  background-color: #0d6efd;
  border-color: #0d6efd;
}
</style>
