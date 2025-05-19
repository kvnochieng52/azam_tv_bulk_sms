<template>
  <DashboardLayout>
    <div class="row">
      <div class="col-12">
        <div class="card">
          <div class="card-header bg-primary text-white">
            <div class="d-flex justify-content-between align-items-center">
              <h3 class="card-title">
                <i class="fas fa-plus-circle me-2"></i>Create New Contact
              </h3>
              <Link
                :href="route('contacts.index')"
                class="btn btn-outline-light btn-sm"
              >
                <i class="fas fa-arrow-left me-1"></i> Back to Contacts
              </Link>
            </div>
          </div>
          <div class="card-body">
            <form @submit.prevent="submit">
              <!-- Contact Group Details -->
              <div class="mb-4">
                <div class="card custom-card mb-4">
                  <div class="card-header bg-info text-white">
                    <h5 class="mb-0">
                      <i class="fas fa-address-card me-2"></i>Contact Group
                      Details
                    </h5>
                  </div>
                  <div class="card-body">
                    <div class="row">
                      <div class="col-md-6">
                        <div class="mb-3">
                          <label for="title" class="form-label fw-bold"
                            >Title <span class="text-danger">*</span></label
                          >
                          <input
                            type="text"
                            id="title"
                            v-model="form.title"
                            class="form-control"
                            :class="{ 'is-invalid': form.errors.title }"
                            placeholder="Enter contact group title"
                          />
                          <div
                            v-if="form.errors.title"
                            class="invalid-feedback"
                          >
                            {{ form.errors.title }}
                          </div>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="mb-3">
                          <label for="is_active" class="form-label fw-bold"
                            >Status</label
                          ><br />
                          <select
                            id="is_active"
                            v-model="form.is_active"
                            class="form-select"
                            :class="{ 'is-invalid': form.errors.is_active }"
                          >
                            <option :value="1">Active</option>
                            <option :value="0">Inactive</option>
                          </select>
                          <div
                            v-if="form.errors.is_active"
                            class="invalid-feedback"
                          >
                            {{ form.errors.is_active }}
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Contact List -->
              <div class="mb-4">
                <div class="card custom-card">
                  <div
                    class="card-header bg-light d-flex justify-content-between align-items-center"
                  >
                    <h5 class="mb-0">
                      <i class="fas fa-users me-2"></i>Contact List
                    </h5>
                    <button
                      type="button"
                      class="btn btn-sm btn-primary"
                      @click="addContact"
                    >
                      <i class="fas fa-plus me-1"></i> Add Contact
                    </button>
                  </div>
                  <div class="card-body">
                    <div
                      v-if="form.contact_lists.length === 0"
                      class="alert alert-info"
                    >
                      <i class="fas fa-info-circle me-2"></i> No contacts added
                      yet. Click the "Add Contact" button to add contacts.
                    </div>

                    <div v-else>
                      <div class="table-responsive">
                        <table class="table table-striped table-hover">
                          <thead class="bg-light">
                            <tr>
                              <th style="width: 45%">
                                <i class="fas fa-user me-1"></i> Name
                              </th>
                              <th style="width: 40%">
                                <i class="fas fa-phone me-1"></i> Telephone
                              </th>
                              <th style="width: 15%" class="text-center">
                                Action
                              </th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr
                              v-for="(contact, index) in form.contact_lists"
                              :key="index"
                            >
                              <td>
                                <input
                                  type="text"
                                  v-model="contact.name"
                                  class="form-control"
                                  :class="{
                                    'is-invalid':
                                      form.errors[
                                        `contact_lists.${index}.name`
                                      ],
                                  }"
                                  placeholder="Contact name"
                                />
                                <div
                                  v-if="
                                    form.errors[`contact_lists.${index}.name`]
                                  "
                                  class="invalid-feedback"
                                >
                                  {{
                                    form.errors[`contact_lists.${index}.name`]
                                  }}
                                </div>
                              </td>
                              <td>
                                <input
                                  type="text"
                                  v-model="contact.telephone"
                                  class="form-control"
                                  :class="{
                                    'is-invalid':
                                      form.errors[
                                        `contact_lists.${index}.telephone`
                                      ],
                                  }"
                                  placeholder="Phone number"
                                />
                                <div
                                  v-if="
                                    form.errors[
                                      `contact_lists.${index}.telephone`
                                    ]
                                  "
                                  class="invalid-feedback"
                                >
                                  {{
                                    form.errors[
                                      `contact_lists.${index}.telephone`
                                    ]
                                  }}
                                </div>
                              </td>
                              <td class="text-center">
                                <button
                                  type="button"
                                  class="btn btn-sm btn-outline-danger"
                                  @click="removeContact(index)"
                                  title="Remove Contact"
                                >
                                  <i class="fas fa-trash"></i>
                                </button>
                              </td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="d-flex justify-content-between mt-4">
                <Link
                  :href="route('contacts.index')"
                  class="btn btn-outline-secondary"
                >
                  <i class="fas fa-times me-1"></i> Cancel
                </Link>
                <button type="submit" class="btn btn-primary px-4">
                  <i class="fas fa-save me-1"></i> Save Contact
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>

<script setup>
import { useForm, Link } from "@inertiajs/vue3";
import DashboardLayout from "@/Layouts/DashboardLayout.vue";

const form = useForm({
  title: "",
  is_active: 1,
  contact_lists: [],
  created_by: null,
  updated_by: null,
});

// Function to add a new contact to the list
function addContact() {
  form.contact_lists.push({
    name: "",
    telephone: "",
  });
}

// Function to remove a contact from the list
function removeContact(index) {
  form.contact_lists.splice(index, 1);
}

// Initialize with an empty contact to avoid errors on first submission
if (form.contact_lists.length === 0) {
  addContact();
}

const submit = () => {
  form.post(route("contacts.store"), {
    onSuccess: () => {
      // Reset form
      form.reset();
    },
  });
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

.custom-card .card-header h5 {
  font-weight: 600;
  margin-bottom: 0;
  text-shadow: 0 1px 1px rgba(0, 0, 0, 0.2);
}

/* Form controls */
.form-control,
.form-select {
  border-radius: 0.375rem;
  border: 1px solid #ced4da;
  padding: 0.5rem 0.75rem;
  transition: all 0.2s;
}

.form-control:focus,
.form-select:focus {
  border-color: #80bdff;
  box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.2);
}

.form-label {
  color: #495057;
  margin-bottom: 0.5rem;
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

/* Button styling */
.btn-sm {
  font-size: 0.8rem;
  padding: 0.25rem 0.75rem;
}

.btn-outline-danger:hover,
.btn-outline-secondary:hover,
.btn-outline-light:hover,
.btn-primary:hover {
  transform: translateY(-1px);
  transition: all 0.2s;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.btn-primary {
  background-color: #0d6efd;
  border-color: #0d6efd;
  box-shadow: 0 2px 4px rgba(13, 110, 253, 0.3);
}

.btn-primary:hover {
  background-color: #0b5ed7;
  border-color: #0a58ca;
}

/* Alert styling */
.alert-info {
  background-color: #f0f7ff;
  border-left: 4px solid #0d6efd;
  border-radius: 0.25rem;
  color: #084298;
  padding: 1rem;
}

.alert-info i {
  color: #0d6efd;
}
</style>
