<template>
  <DashboardLayout>
    <div class="row mb-3">
      <div class="col-12">
        <div class="card">
          <div class="card-header">
            <div
              class="d-flex justify-content-between align-items-center w-100"
            >
              <h4 class="mb-0">User Management</h4>
              <Link href="/users/create" class="btn btn-primary btn-sm">
                <i class="fas fa-plus me-1"></i> Create User
              </Link>
            </div>
          </div>
          <div class="card-body">
            <!-- Search and Filters Card -->
            <div class="card shadow-sm mb-4">
              <div class="card-body p-3">
                <div class="row g-3 align-items-end">
                  <!-- Search Input -->
                  <div class="col-md-4">
                    <label for="search" class="form-label small text-muted mb-1"
                      >Search Users</label
                    >
                    <div class="input-group input-group-sm">
                      <span class="input-group-text bg-white">
                        <i class="fas fa-search text-muted"></i>
                      </span>
                      <input
                        id="search"
                        type="text"
                        class="form-control form-control-sm"
                        placeholder="Name, email or phone..."
                        v-model="filters.search"
                        @input="debouncedSearch"
                      />
                      <button
                        v-if="filters.search"
                        class="btn btn-outline-secondary btn-sm"
                        type="button"
                        @click="resetSearch"
                      >
                        <i class="fas fa-times"></i>
                      </button>
                    </div>
                  </div>

                  <!-- Role Filter -->
                  <div class="col-md-3">
                    <label for="role" class="form-label small text-muted mb-1"
                      >Filter by Role</label
                    >
                    <select
                      id="role"
                      class="form-select form-select-sm"
                      v-model="filters.role"
                      @change="applyFilters"
                    >
                      <option value="">All Roles</option>
                      <option
                        v-for="role in roles"
                        :value="role.name"
                        :key="role.id"
                      >
                        {{ role.name }}
                      </option>
                    </select>
                  </div>

                  <!-- Status Filter -->
                  <div class="col-md-3">
                    <label for="status" class="form-label small text-muted mb-1"
                      >Filter by Status</label
                    >
                    <select
                      id="status"
                      class="form-select form-select-sm"
                      v-model="filters.status"
                      @change="applyFilters"
                    >
                      <option value="">All Statuses</option>
                      <option value="active">Active</option>
                      <option value="inactive">Inactive</option>
                    </select>
                  </div>

                  <!-- Actions -->
                  <div class="col-md-2 d-flex">
                    <button
                      class="btn btn-outline-secondary btn-sm w-100"
                      @click="resetFilters"
                      :disabled="!hasActiveFilters"
                    >
                      <i class="fas fa-filter-circle-xmark me-1"></i> Reset
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Users Table -->
            <div class="card shadow-sm">
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table class="table table-hover mb-0">
                    <!-- Table Head -->
                    <thead class="table-light">
                      <tr>
                        <th width="50">#</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Role</th>
                        <th width="120">Status</th>
                        <th width="120">Actions</th>
                      </tr>
                    </thead>

                    <!-- Table Body -->
                    <tbody>
                      <tr v-for="(user, index) in users.data" :key="user.id">
                        <td class="text-muted">{{ users.from + index }}</td>
                        <td>
                          <Link
                            :href="`/users/${user.id}/edit`"
                            class="text-decoration-none d-flex align-items-center"
                          >
                            <!-- <span
                              class="avatar avatar-xs me-2 bg-light rounded-circle text-dark d-flex align-items-center justify-content-center"
                            >
                              {{ user.name.charAt(0).toUpperCase() }}
                            </span> -->
                            <b>{{ user.name }}</b>
                          </Link>
                        </td>
                        <td>{{ user.email }}</td>
                        <td>{{ user.telephone || "-" }}</td>
                        <td>
                          <span class="badge bg-light text-dark">
                            {{ user.roles[0]?.name || "No role" }}
                          </span>
                        </td>
                        <td>
                          <span
                            class="badge rounded-pill"
                            :class="{
                              'bg-success bg-opacity-10 text-success':
                                user.is_active,
                              'bg-danger bg-opacity-10 text-danger':
                                !user.is_active,
                            }"
                          >
                            <i
                              class="fas fa-circle me-1"
                              style="font-size: 6px; vertical-align: middle"
                              :class="{
                                'text-success': user.is_active,
                                'text-danger': !user.is_active,
                              }"
                            ></i>
                            {{ user.is_active ? "Active" : "Inactive" }}
                          </span>
                        </td>
                        <td>
                          <div class="d-flex">
                            <Link
                              :href="`/users/${user.id}/edit`"
                              class="btn btn-icon btn-sm btn-light rounded-circle me-1"
                              data-bs-toggle="tooltip"
                              title="Edit"
                            >
                              <i class="fas fa-pencil-alt text-primary"></i>
                            </Link>
                            <button
                              @click="deleteUser(user.id)"
                              class="btn btn-icon btn-sm btn-light rounded-circle"
                              data-bs-toggle="tooltip"
                              title="Delete"
                            >
                              <i class="fas fa-trash-alt text-danger"></i>
                            </button>
                          </div>
                        </td>
                      </tr>
                      <tr v-if="users.data.length === 0">
                        <td colspan="7" class="text-center py-4">
                          <div class="d-flex flex-column align-items-center">
                            <i
                              class="fas fa-user-slash text-muted mb-2"
                              style="font-size: 1.5rem"
                            ></i>
                            <p class="text-muted mb-0">No users found</p>
                            <button
                              v-if="hasActiveFilters"
                              class="btn btn-link btn-sm mt-2"
                              @click="resetFilters"
                            >
                              Clear filters
                            </button>
                          </div>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>

            <!-- Pagination -->
            <div class="col-sm-12 col-md-12">
              <div
                class="dataTables_paginate paging_simple_numbers d-flex justify-content-end"
              >
                <Pagination :links="users.links" />
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>

<script setup>
import DashboardLayout from "@/Layouts/DashboardLayout.vue";
import { Link, router } from "@inertiajs/vue3";
import { ref, computed } from "vue";
import { debounce } from "lodash";
import Toast from "@/Services/toast";
import Pagination from "@/Components/Pagination.vue";

const props = defineProps({
  users: Object,
  roles: Array,
  filters: Object,
});

const filters = ref({
  search: props.filters.search || "",
  role: props.filters.role || "",
  status: props.filters.status || "",
});

const hasActiveFilters = computed(() => {
  return filters.value.search || filters.value.role || filters.value.status;
});

const debouncedSearch = debounce(() => {
  applyFilters();
}, 500);

const applyFilters = () => {
  router.get("/users", filters.value, {
    preserveState: true,
    replace: true,
  });
};

const resetFilters = () => {
  filters.value = {
    search: "",
    role: "",
    status: "",
  };
  applyFilters();
};

const resetSearch = () => {
  filters.value.search = "";
  applyFilters();
};

const deleteUser = (id) => {
  if (confirm("Are you sure you want to delete this user?")) {
    router.delete(`/users/${id}`, {
      preserveScroll: true,
      onSuccess: () => {
        // Optional: Show a success message
        Toast.success("User deleted successfully.");
      },
    });
  }
};
</script>

<style scoped>
.card-header {
  background-color: #f8f9fa;
  border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

.avatar {
  width: 28px;
  height: 28px;
  font-size: 0.75rem;
}

.table-hover tbody tr:hover {
  background-color: rgba(13, 110, 253, 0.02);
}

.page-item.active .page-link {
  background-color: #0d6efd;
  border-color: #0d6efd;
}

.page-link {
  color: #495057;
  min-width: 32px;
  text-align: center;
}

.btn-icon {
  width: 28px;
  height: 28px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.badge {
  font-weight: 500;
  padding: 0.35em 0.65em;
}

.table-light th {
  font-weight: 500;
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #6c757d;
  background-color: #f8f9fa;
}

.form-control-sm,
.form-select-sm {
  font-size: 0.8125rem;
  padding: 0.25rem 0.5rem;
}

.input-group-text {
  padding: 0.25rem 0.5rem;
}
</style>