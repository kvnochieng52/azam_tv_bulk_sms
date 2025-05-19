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
            <!-- Search functionality -->
            <div class="dataTables_wrapper dt-bootstrap4">
              <div class="row mb-3">
                <div class="col-sm-12 col-md-6">
                  <div class="dataTables_length">
                    <label class="d-flex align-items-center">
                      Search:
                      <input
                        type="search"
                        class="form-control form-control-sm ms-2"
                        placeholder="Search SMS messages..."
                        v-model="searchTerm"
                        @input="filterTexts"
                      />
                    </label>
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
                      <td>{{ text.contacts_count }}</td>
                      <td>{{ text.creator?.name }}</td>
                      <td>{{ formatDate(text.created_at) }}</td>
                      <td>
                        <div class="btn-group">
                          <a
                            :href="route('sms.show', text.id)"
                            class="btn btn-info btn-sm"
                          >
                            <i class="fas fa-eye"></i>
                          </a>
                          <a
                            v-if="text.status_id <= 1"
                            :href="route('sms.edit', text.id)"
                            class="btn btn-primary btn-sm"
                          >
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
                </table>
              </div>

              <!-- Pagination - only show when not filtering -->
              <div
                class="row mt-3"
                v-if="!isSearching && texts.data.length > 0"
              >
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
  </DashboardLayout>
</template>

<script setup>
import { ref } from "vue";
import { router } from "@inertiajs/vue3";
import DashboardLayout from "@/Layouts/DashboardLayout.vue";
import Pagination from "@/Components/Pagination.vue";
import { Link } from "@inertiajs/vue3";

// Props from controller
const props = defineProps({
  texts: Object,
});

// Search functionality
const searchTerm = ref("");
const filteredTexts = ref([]);
const isSearching = ref(false);

// Initialize filtered texts with all texts
filteredTexts.value = props.texts.data;

// Function to filter texts based on search term
const filterTexts = () => {
  if (!searchTerm.value.trim()) {
    // If search term is empty, show all texts
    filteredTexts.value = props.texts.data;
    isSearching.value = false;
    return;
  }

  isSearching.value = true;
  const term = searchTerm.value.toLowerCase().trim();

  // Filter texts based on search term matching title, status, or date
  filteredTexts.value = props.texts.data.filter((text) => {
    return (
      (text.text_title && text.text_title.toLowerCase().includes(term)) ||
      (text.status?.text_status_name &&
        text.status.text_status_name.toLowerCase().includes(term)) ||
      (text.scheduled && "scheduled".includes(term)) ||
      (!text.scheduled && "immediate".includes(term)) ||
      formatDate(text.created_at).toLowerCase().includes(term)
    );
  });
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
</style>
