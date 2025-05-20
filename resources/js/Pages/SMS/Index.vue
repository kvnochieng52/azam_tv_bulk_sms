<template>
  <DashboardLayout>
    <div class="row">
      <div class="col-12">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">
              <i class="far fa-list-alt nav-icon"></i> &nbsp; SMS Messages
            </h3>
            <div class="card-tools">
              <a :href="route('sms.create')" class="btn btn-primary btn-sm">
                <i class="fas fa-plus mr-1"></i> New SMS
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
                        placeholder="Search SMS messages..."
                        v-model="search"
                        @input="performSearch"
                      />
                    </label>
                  </div>
                </div>
                <div class="col-sm-12 col-md-6 text-right">
                  <div class="btn-group">
                    <a
                      :href="route('sms.export.csv', { search: search })"
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
                          <a
                            v-if="text.status_id <= 1"
                            :href="route('sms.edit', text.id)"
                            class="btn btn-outline-primary btn-sm me-1"
                          >
                            <i class="fas fa-edit"></i>
                          </a>
                          <button
                            v-if="text.status_id <= 2"
                            @click="confirmDelete(text)"
                            class="btn btn-outline-danger btn-sm"
                          >
                            <i class="fas fa-trash"></i>
                          </button>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <!-- Pagination - always show when there are results -->
              <div class="row mt-3" v-if="texts.data.length > 0">
                <div class="col-sm-12 col-md-5">
                  <div class="dataTables_info">
                    Showing {{ texts.from }} to {{ texts.to }} of
                    {{ texts.total }} entries
                  </div>
                </div>
                <div class="col-sm-12 col-md-7">
                  <div class="dataTables_paginate paging_simple_numbers">
                    <pagination :links="texts.links" />
                  </div>
                </div>
              </div>

              <!-- Show result count when filtering -->
              <div class="row mt-3" v-if="isSearching && searchTerm">
                <div class="col-12">
                  <div class="dataTables_info">
                    Found {{ filteredTexts.length }} matching results for "{{
                      searchTerm
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
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Confirm Delete</h5>
            <button
              type="button"
              class="btn-close"
              data-bs-dismiss="modal"
              aria-label="Close"
            ></button>
          </div>
          <div class="modal-body">
            <p>
              Are you sure you want to delete the SMS
              <strong>"{{ textToDelete?.text_title }}"</strong>?
            </p>
            <p class="text-danger">This action cannot be undone.</p>
          </div>
          <div class="modal-footer">
            <button
              type="button"
              class="btn btn-secondary"
              data-bs-dismiss="modal"
            >
              Cancel
            </button>
            <button
              type="button"
              class="btn btn-danger"
              @click="deleteText"
              :disabled="isDeleting"
            >
              <i class="fas fa-trash mr-1"></i> Delete
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Contact Details Modal -->
    <div
      class="modal"
      :class="{ 'd-block': showContactDetailModal }"
      tabindex="-1"
      role="dialog"
    >
      <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
          <div class="modal-header bg-info text-white">
            <h5 class="modal-title">
              <i class="fas fa-address-card mr-2"></i> Contact Details
            </h5>
            <button
              type="button"
              class="close"
              @click="showContactDetailModal = false"
              aria-label="Close"
            >
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <div v-if="selectedContact" class="card">
              <div class="card-body">
                <h5 class="card-title">{{ selectedContact.full_name }}</h5>
                <p class="card-text">
                  <i class="fas fa-phone text-primary mr-2"></i>
                  <strong>Primary Phone:</strong>
                  {{ selectedContact.phone_number }}
                </p>

                <!-- Contact Lists -->
                <div
                  v-if="
                    selectedContact.lists && selectedContact.lists.length > 0
                  "
                >
                  <hr />
                  <h6>
                    <i class="fas fa-list-ul mr-2"></i> Associated Contact
                    Lists:
                  </h6>
                  <div class="list-group">
                    <div
                      v-for="list in selectedContact.lists"
                      :key="list.id"
                      class="list-group-item list-group-item-action flex-column align-items-start"
                    >
                      <div class="d-flex w-100 justify-content-between">
                        <h6 class="mb-1">{{ list.name }}</h6>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <a
              v-if="selectedContact"
              :href="route('contacts.show', selectedContact.id)"
              class="btn btn-primary"
            >
              <i class="fas fa-external-link-alt mr-1"></i> View Full Details
            </a>
            <button
              type="button"
              class="btn btn-secondary"
              @click="showContactDetailModal = false"
            >
              Close
            </button>
          </div>
        </div>
      </div>
      <!-- Modal backdrop -->
      <div
        v-if="showContactDetailModal"
        class="modal-backdrop fade show"
        @click="showContactDetailModal = false"
      ></div>
    </div>
  </DashboardLayout>
</template>

<script setup>
import { ref, computed, nextTick } from "vue";
import { router } from "@inertiajs/vue3";
import axios from "axios";
import DashboardLayout from "@/Layouts/DashboardLayout.vue";
import Pagination from "@/Components/Pagination.vue";
import { Link } from "@inertiajs/vue3";

// Props from controller
const props = defineProps({
  texts: Object,
  filters: Object,
});

// Server-side search functionality
const search = ref(props.filters?.search || "");

// For template access
const filteredTexts = computed(() => props.texts.data);

// Debounce search to prevent excessive API calls
let searchTimeout = null;
const performSearch = () => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    router.get(
      route("sms.index"),
      { search: search.value },
      { preserveState: true, replace: true }
    );
  }, 500); // 500ms delay
};

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

// Organize manual contacts into three balanced columns for display
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
      // Step 2: Extract contact IDs from contact_list
      let contactIds = [];

      if (text.contact_list) {
        // Try parsing as JSON first
        try {
          contactIds = JSON.parse(text.contact_list);
        } catch (e) {
          // If JSON parsing fails, split by comma
          contactIds = text.contact_list
            .split(",")
            .map((id) => id.trim())
            .filter(Boolean);
        }
      }

      console.log("Extracted contact IDs:", contactIds);

      if (contactIds.length > 0) {
        try {
          // Step 3: Make axios request to get contacts
          const response = await axios.post(route("contacts.get-contacts"), {
            contact_ids: contactIds,
          });

          console.log("API response:", response.data);

          // Step 4: Store contacts for display
          if (response.data && response.data.contacts) {
            contactsList.value = response.data.contacts;
          } else {
            console.error("No contacts returned from API");
          }
        } catch (error) {
          console.error("Error fetching contacts:", error);
        }
      }

      loadingContacts.value = false;
    } else if (text.contact_type === "csv") {
      // For CSV, just show download link
      loadingContacts.value = false;
    }
  } catch (error) {
    console.error("Error in viewContacts:", error);
    loadingContacts.value = false;
  }
};

// Delete functionality
const textToDelete = ref(null);
const isDeleting = ref(false);

const confirmDelete = (text) => {
  textToDelete.value = text;
  // Show modal
  const modal = new bootstrap.Modal(document.getElementById("deleteModal"));
  modal.show();
};

const deleteText = () => {
  if (textToDelete.value) {
    isDeleting.value = true;
    router.delete(route("sms.destroy", textToDelete.value.id), {
      onSuccess: () => {
        // Close modal
        bootstrap.Modal.getInstance(
          document.getElementById("deleteModal")
        ).hide();
        isDeleting.value = false;
        textToDelete.value = null;
      },
      onError: () => {
        isDeleting.value = false;
      },
    });
  }
};

// Format date for display
const formatDate = (dateString) => {
  if (!dateString) return "";
  const date = new Date(dateString);
  return date.toLocaleString();
};
</script>


<style scoped>
thead {
  border-top: 1px solid #e3e3e3;
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
</style>
