<template>
  <DashboardLayout>
    <div class="row">
      <div class="col-12">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">
              <i class="far fa-clock nav-icon"></i> &nbsp; Scheduled SMS
              Messages
            </h3>
            <div class="card-tools">
              <a :href="route('sms.create')" class="btn btn-primary btn-sm">
                <i class="fas fa-plus mr-1"></i> New Scheduled SMS
              </a>
            </div>
          </div>
          <div class="card-body">
            <!-- Search functionality -->
            <div class="dataTables_wrapper dt-bootstrap4">
              <div class="row mb-3">
                <div class="col-sm-12 col-md-6">
                  <div class="dataTables_length">
                    <label class="d-flex align-items-center">
                      Search:
                      <input
                        type="search"
                        class="form-control form-control-sm ms-2 ml-2"
                        placeholder="Search scheduled messages..."
                        v-model="search"
                        @input="performSearch"
                      />
                    </label>
                  </div>
                </div>
                <div class="col-sm-12 col-md-6 text-right">
                  <div class="btn-group">
                    <a
                      :href="
                        route('sms.export.csv', {
                          search: search,
                          scheduled: 1,
                        })
                      "
                      class="btn btn-success btn-sm"
                    >
                      <i class="fas fa-file-csv mr-1"></i> Export to CSV
                    </a>
                  </div>
                </div>
              </div>

              <div class="table-responsive">
                <table class="table table-bordered table-striped">
                  <thead>
                    <tr>
                      <th>Title</th>
                      <th>Status</th>
                      <th>Schedule Date</th>
                      <th>Contacts</th>
                      <th>Created By</th>
                      <th>Created At</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody v-if="filteredTexts.length > 0">
                    <tr v-for="text in filteredTexts" :key="text.id">
                      <td>
                        <b
                          ><Link :href="route('sms.show', text.id)">{{
                            text.text_title
                          }}</Link></b
                        >
                      </td>
                      <td>
                        <span
                          class="badge"
                          :class="'bg-' + text.status?.color_code"
                        >
                          {{ text.status?.text_status_name }}
                        </span>
                      </td>
                      <td>
                        <span class="badge bg-info p-2">
                          {{ formatDate(text.schedule_date) }}
                        </span>
                      </td>
                      <td>
                        <div class="d-flex align-items-center">
                          <button
                            class="btn btn-sm btn-outline-info ml-2"
                            @click.prevent="viewContacts(text)"
                            title="View Contacts"
                          >
                            <i class="fas fa-eye"></i> view
                          </button>
                        </div>
                      </td>
                      <td>{{ text.creator?.name }}</td>
                      <td>{{ formatDate(text.created_at) }}</td>
                      <td>
                        <div class="btn-group">
                          <a
                            :href="route('sms.show', text.id)"
                            class="btn btn-outline-info btn-sm"
                          >
                            <i class="fas fa-eye"></i>
                          </a>
                          <!-- <a
                            :href="route('sms.edit', text.id)"
                            class="btn btn-outline-warning btn-sm"
                          >
                            <i class="fas fa-edit"></i>
                          </a> -->
                          <button
                            @click="confirmDelete(text)"
                            class="btn btn-outline-danger btn-sm"
                          >
                            <i class="fas fa-trash"></i>
                          </button>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                  <tbody v-else>
                    <tr>
                      <td colspan="7" class="text-center">
                        No scheduled SMS messages found.
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <div class="row mt-3" v-if="texts.data.length > 0">
                <div class="col-sm-12 col-md-5">
                  <div class="dataTables_info">
                    Showing {{ texts.from }} to {{ texts.to }} of
                    {{ texts.total }} entries
                  </div>
                </div>
                <div class="col-sm-12 col-md-7">
                  <div
                    class="dataTables_paginate paging_simple_numbers d-flex justify-content-end"
                  >
                    <pagination :links="texts.links" />
                  </div>
                </div>
              </div>

              <!-- Show result count when filtering -->
              <div class="row mt-3" v-if="search">
                <div class="col-12">
                  <div class="dataTables_info">
                    Found {{ filteredTexts.length }} matching results for "{{
                      search
                    }}"
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Backdrop -->
    <div v-if="showContactsModal" class="modal-backdrop fade show"></div>

    <!-- Contacts View Modal -->
    <div
      v-if="showContactsModal"
      class="modal show"
      tabindex="-1"
      style="display: block"
      aria-hidden="true"
    >
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">
              <i class="fas fa-address-book mr-2"></i>
              Contacts for: {{ selectedText?.text_title }}
            </h5>
            <button
              type="button"
              class="close"
              @click="showContactsModal = false"
              aria-label="Close"
            >
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <div v-if="loadingContacts" class="text-center p-5">
              <div class="spinner-border text-primary" role="status">
                <span class="sr-only">Loading...</span>
              </div>
              <p class="mt-2">Loading contacts...</p>
            </div>

            <div v-else>
              <!-- Contact Type Information -->
              <div class="alert" :class="contactTypeAlertClass">
                <i :class="contactTypeIcon"></i>
                <strong>Contact Type:</strong> {{ contactTypeLabel }}
              </div>

              <!-- Manual Entry Contacts -->
              <div
                v-if="
                  selectedText?.contact_type === 'manual' &&
                  selectedText?.recepient_contacts
                "
              >
                <div class="card card-body bg-light mb-3">
                  <div class="row">
                    <template
                      v-for="(
                        contactGroup, columnIndex
                      ) in manualContactColumns"
                      :key="columnIndex"
                    >
                      <div class="col-md-4">
                        <ul class="list-unstyled contact-list">
                          <li
                            v-for="(contact, index) in contactGroup"
                            :key="index"
                            class="mb-2"
                          >
                            <i class="fas fa-mobile-alt text-primary mr-2"></i>
                            {{ contact }}
                          </li>
                        </ul>
                      </div>
                    </template>
                  </div>
                </div>
              </div>

              <!-- CSV Upload Contacts -->
              <div v-else-if="selectedText?.contact_type === 'csv'">
                <p class="mb-3">
                  <i class="fas fa-file-csv mr-2"></i>
                  Contacts were uploaded via CSV file.
                </p>
                <a
                  v-if="selectedText?.csv_file_path"
                  :href="
                    route('sms.download.csv', [selectedText.csv_file_name])
                  "
                  class="btn btn-primary"
                  download
                >
                  <i class="fas fa-download mr-2"></i>
                  Download Original CSV File
                </a>
                <div v-else class="alert alert-warning">
                  <i class="fas fa-exclamation-triangle mr-2"></i>
                  The original CSV file is not available.
                </div>
              </div>

              <!-- Contact List Contacts -->
              <div v-else-if="selectedText?.contact_type === 'list'">
                <div v-if="loadingContacts" class="text-center py-4">
                  <div class="spinner-border text-primary" role="status">
                    <span class="sr-only">Loading...</span>
                  </div>
                  <p class="mt-2">Loading contacts from selected lists...</p>
                </div>

                <div
                  v-else-if="contactsList.length > 0"
                  class="card card-body bg-light mb-3"
                >
                  <div class="table-responsive">
                    <table class="table table-striped">
                      <thead>
                        <tr>
                          <th>#</th>
                          <th>Contact Name</th>
                          <th>Contacts Count</th>
                          <th>Actions</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr
                          v-for="(contact, index) in contactsList"
                          :key="contact.id || index"
                        >
                          <td>{{ index + 1 }}</td>
                          <td>
                            <i class="fas fa-users text-primary mr-2"></i>
                            {{ contact.full_name }}
                          </td>
                          <td>
                            <span class="badge badge-info">
                              {{ contact.contacts_count || 0 }}
                            </span>
                          </td>
                          <td>
                            <a
                              :href="route('contacts.show', contact.id)"
                              class="btn btn-sm btn-info"
                              target="_blank"
                            >
                              <i class="fas fa-eye"></i> View
                            </a>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
                <div v-else-if="!loadingContacts" class="alert alert-warning">
                  <i class="fas fa-exclamation-triangle mr-2"></i>
                  No contacts found for the selected list(s).
                </div>
              </div>

              <!-- No Contacts -->
              <div v-else class="alert alert-info">
                <i class="fas fa-info-circle mr-2"></i>
                No contact information is available.
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button
              type="button"
              class="btn btn-secondary"
              @click="showContactsModal = false"
            >
              Close
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div
      class="modal fade"
      :class="{ show: showDeleteModal }"
      style="display: block"
      v-if="showDeleteModal"
    >
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header bg-danger text-white">
            <h5 class="modal-title">
              <i class="fas fa-exclamation-triangle mr-2"></i>
              Confirm Delete
            </h5>
            <button
              type="button"
              class="close text-white"
              aria-label="Close"
              @click="showDeleteModal = false"
            >
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <p>
              Are you sure you want to delete the scheduled SMS
              <strong>{{ textToDelete?.text_title }}</strong
              >?
            </p>
            <p>This action cannot be undone.</p>
          </div>
          <div class="modal-footer">
            <button
              type="button"
              class="btn btn-secondary"
              @click="showDeleteModal = false"
            >
              Cancel
            </button>
            <button
              type="button"
              class="btn btn-danger"
              @click="deleteText"
              :disabled="deleting"
            >
              <span v-if="deleting">
                <i class="fas fa-spinner fa-spin mr-1"></i> Deleting...
              </span>
              <span v-else> <i class="fas fa-trash mr-1"></i> Delete </span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Backdrop for Delete Modal -->
    <div v-if="showDeleteModal" class="modal-backdrop fade show"></div>

    <!-- Delete Confirmation Modal -->
    <div
      class="modal fade"
      :class="{ show: showDeleteModal }"
      style="display: block"
      v-if="showDeleteModal"
    >
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">
              <i class="fas fa-exclamation-triangle text-danger mr-2"></i>
              Confirm Delete
            </h5>
            <button
              type="button"
              class="close"
              aria-label="Close"
              @click="showDeleteModal = false"
            >
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <p>
              Are you sure you want to delete the scheduled SMS:
              <strong>{{ textToDelete?.text_title }}</strong
              >?
            </p>
            <p class="text-danger">
              <i class="fas fa-exclamation-circle mr-1"></i>
              This action cannot be undone.
            </p>
          </div>
          <div class="modal-footer">
            <button
              type="button"
              class="btn btn-secondary"
              @click="showDeleteModal = false"
            >
              Cancel
            </button>
            <button
              type="button"
              class="btn btn-danger"
              @click="deleteText"
              :disabled="deleting"
            >
              <span v-if="deleting">
                <i class="fas fa-spinner fa-spin mr-1"></i> Deleting...
              </span>
              <span v-else> <i class="fas fa-trash mr-1"></i> Delete </span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { Link, router } from "@inertiajs/vue3";
import Toast from "@/Services/toast";
import axios from "axios";
import DashboardLayout from "@/Layouts/DashboardLayout.vue";
import Pagination from "@/Components/Pagination.vue";
// If we need the progress donut component, uncomment this:
// import ProgressDonut from '@/Components/ProgressDonut.vue';

const props = defineProps({
  texts: Object,
  filters: Object,
});

// Search functionality
const search = ref(props.filters.search || "");
const performSearch = () => {
  router.get(
    route("sms.scheduled"),
    { search: search.value },
    { preserveState: true, replace: true }
  );
};

// Computed properties
const filteredTexts = computed(() => {
  return props.texts.data;
});

// Contacts view functionality
const selectedText = ref(null);
const showContactsModal = ref(false);
const loadingContacts = ref(false);
const contactsList = ref([]);
const selectedContact = ref(null);
const showContactDetailModal = ref(false);

const showContactDetails = (contact) => {
  selectedContact.value = contact;
  showContactDetailModal.value = true;
};

// Contact type computed properties
const contactTypeLabel = computed(() => {
  if (!selectedText.value) return "";

  switch (selectedText.value.contact_type) {
    case "manual":
      return "Manual Entry";
    case "csv":
      return "CSV Upload";
    case "list":
      return "Contact List";
    default:
      return "Unknown";
  }
});

// Parse contact IDs from the selectedText for list type SMS
const parsedContactListIds = computed(() => {
  if (
    !selectedText.value ||
    selectedText.value.contact_type !== "list" ||
    !selectedText.value.contact_list
  ) {
    return [];
  }

  // Try to parse contact_list as JSON first
  try {
    // Try to parse as JSON (handles the ["2","3"] format)
    return JSON.parse(selectedText.value.contact_list);
  } catch (e) {
    console.log(
      "Error parsing contact list JSON, trying comma-separated format:",
      e
    );
    // If parsing fails, treat as comma-separated values
    return selectedText.value.contact_list
      .split(",")
      .map((id) => id.trim())
      .filter(Boolean);
  }
});

// Organize manual contacts into three balanced columns for display
const manualContactColumns = computed(() => {
  if (
    !selectedText.value ||
    selectedText.value.contact_type !== "manual" ||
    !selectedText.value.recepient_contacts
  ) {
    return [[], [], []];
  }

  // Split the comma-separated string into an array and filter out empty entries
  const contacts = selectedText.value.recepient_contacts
    .split(",")
    .map((contact) => contact.trim())
    .filter(Boolean);

  // Create three balanced columns
  const columns = [[], [], []];
  const contactsPerColumn = Math.ceil(contacts.length / 3);

  // Distribute contacts evenly across columns
  contacts.forEach((contact, index) => {
    const columnIndex = Math.floor(index / contactsPerColumn);
    if (columnIndex < 3) {
      columns[columnIndex].push(contact);
    } else {
      // If we have more than can fit in 3 columns, add to the last column
      columns[2].push(contact);
    }
  });

  return columns;
});

const contactTypeIcon = computed(() => {
  if (!selectedText.value) return "fas fa-question-circle mr-2";

  switch (selectedText.value.contact_type) {
    case "manual":
      return "fas fa-keyboard mr-2";
    case "csv":
      return "fas fa-file-csv mr-2";
    case "list":
      return "fas fa-list-alt mr-2";
    default:
      return "fas fa-question-circle mr-2";
  }
});

const contactTypeAlertClass = computed(() => {
  if (!selectedText.value) return "alert-secondary";

  switch (selectedText.value.contact_type) {
    case "manual":
      return "alert-info";
    case "csv":
      return "alert-info";
    case "list":
      return "alert-info";
    default:
      return "alert-info";
  }
});

// Function to view contacts for a text
const viewContacts = async (text) => {
  // Step 1: Set selected text and show loading state
  selectedText.value = text;
  loadingContacts.value = true;
  contactsList.value = [];

  // Show modal first
  showContactsModal.value = true;

  try {
    // Step 2: Handle contact type
    if (text.contact_type === "manual") {
      // For manual entries, contacts are already in recepient_contacts
      loadingContacts.value = false;
    } else if (text.contact_type === "list") {
      // For list type, we need to fetch the contact lists
      // If this were a real implementation, we would fetch from the server
      // For now, let's just simulate the lists
      contactsList.value = [];
      loadingContacts.value = false;
    } else {
      // For CSV uploads or any other type
      loadingContacts.value = false;
    }
  } catch (error) {
    console.error("Error fetching contact data:", error);
    loadingContacts.value = false;
  }
};

// Delete functionality
const textToDelete = ref(null);
const showDeleteModal = ref(false);
const deleting = ref(false);

const confirmDelete = (text) => {
  textToDelete.value = text;
  showDeleteModal.value = true;
};

const deleteText = () => {
  if (!textToDelete.value) return;

  deleting.value = true;
  const title = textToDelete.value.text_title;
  const id = textToDelete.value.id;

  // Use axios directly for better control over the response
  axios
    .delete(route("sms.destroy", id), {
      headers: {
        "X-Requested-With": "XMLHttpRequest",
        Accept: "application/json",
      },
    })
    .then((response) => {
      showDeleteModal.value = false;
      deleting.value = false;

      // Show success toast and remove the item from the list
      Toast.success(
        response.data.message ||
          `Scheduled SMS '${title}' deleted successfully.`
      );

      // Remove the deleted SMS from the list without a full page reload
      const index = props.texts.data.findIndex((item) => item.id === id);
      if (index !== -1) {
        props.texts.data.splice(index, 1);
      }
    })
    .catch((error) => {
      deleting.value = false;

      // Show error toast with server message if available
      const errorMessage =
        error.response?.data?.message ||
        `Failed to delete SMS '${title}'. Please try again.`;
      Toast.error(errorMessage);
    });
};

// Helper functions
const formatDate = (dateString) => {
  if (!dateString) return "";
  const date = new Date(dateString);
  return date.toLocaleString();
};

const parseContacts = (contactsString) => {
  if (!contactsString) return [];
  return contactsString.split(",").map((contact) => contact.trim());
};

const formatColumns = (columnsJson) => {
  try {
    return JSON.parse(columnsJson);
  } catch (e) {
    return [];
  }
};

const parseContactLists = (listsJson) => {
  try {
    const lists = JSON.parse(listsJson);
    // In a real application, you would probably fetch the names of these lists
    // For now, we'll just return generic names
    return lists.map((id) => `List ${id}`);
  } catch (e) {
    return [];
  }
};
</script>

<style scoped>
thead {
  background-color: #f4f6f9;
}

.card-header {
  background: linear-gradient(135deg, #007bff, #005bb5);
  border-bottom: none;
  padding: 1rem 1.5rem;
}

.card-title {
  color: white;
  font-weight: 600;
  margin-bottom: 0;
  font-size: 1.25rem;
  display: flex;
  align-items: center;
}

.modal {
  overflow-y: auto;
}

.badge {
  font-size: 0.8rem;
  padding: 0.35em 0.65em;
}

.btn-group .btn {
  margin-right: 2px;
}

.contacts-table {
  max-height: 400px;
  overflow-y: auto;
}
</style>
