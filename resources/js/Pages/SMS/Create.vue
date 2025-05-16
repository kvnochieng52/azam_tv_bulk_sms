<template>
  <DashboardLayout>
    <div class="row">
      <div class="col-12">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">New SMS</h3>
          </div>
          <div class="card-body">
            <form @submit.prevent="submitForm">
              <!-- Title of SMS / Campaign -->
              <div class="form-group mb-4">
                <label for="text_title" class="form-label fw-bold">Title of SMS / Campaign<span class="text-danger">*</span></label>
                <input 
                  type="text" 
                  id="text_title" 
                  v-model="form.text_title" 
                  class="form-control" 
                  :class="{ 'is-invalid': errors.text_title }"
                  placeholder="Enter the SMS/Campaign Title"
                />
                <div v-if="errors.text_title" class="invalid-feedback">{{ errors.text_title }}</div>
              </div>

              <!-- Contact Source -->
              <div class="form-group mb-4">
                <label class="form-label fw-bold">Select Contact Source<span class="text-danger">*</span></label>
                <div class="mt-2">
                  <div class="form-check form-check-inline">
                    <input 
                      class="form-check-input" 
                      type="radio" 
                      id="contact_type_manual" 
                      v-model="form.contact_type" 
                      value="manual"
                    />
                    <label class="form-check-label" for="contact_type_manual">Enter Recipient Contacts</label>
                  </div>
                  <div class="form-check form-check-inline">
                    <input 
                      class="form-check-input" 
                      type="radio" 
                      id="contact_type_csv" 
                      v-model="form.contact_type" 
                      value="csv"
                    />
                    <label class="form-check-label" for="contact_type_csv">Import from a CSV File</label>
                  </div>
                  <div class="form-check form-check-inline">
                    <input 
                      class="form-check-input" 
                      type="radio" 
                      id="contact_type_list" 
                      v-model="form.contact_type" 
                      value="list"
                    />
                    <label class="form-check-label" for="contact_type_list">From a Saved Contact List</label>
                  </div>
                </div>
                <div v-if="errors.contact_type" class="text-danger mt-1">{{ errors.contact_type }}</div>
              </div>

              <!-- Dynamic input based on contact type selection -->
              <div class="form-group mb-4">
                <!-- Manual input - comma separated contacts -->
                <div v-if="form.contact_type === 'manual'">
                  <label for="recepient_contacts" class="form-label fw-bold">Enter Contacts (comma-separated)<span class="text-danger">*</span></label>
                  <textarea 
                    id="recepient_contacts" 
                    v-model="form.recepient_contacts" 
                    class="form-control" 
                    :class="{ 'is-invalid': errors.recepient_contacts }"
                    rows="2" 
                    placeholder="Enter the contacts comma separated e.g. 0712345678,0714345678"
                  ></textarea>
                  <div v-if="errors.recepient_contacts" class="invalid-feedback">{{ errors.recepient_contacts }}</div>
                </div>

                <!-- CSV file upload -->
                <div v-if="form.contact_type === 'csv'">
                  <label class="form-label fw-bold">&nbsp;Upload CSV File<span class="text-danger">*</span></label>
                  <div class="input-group">
                    <button 
                      type="button" 
                      class="btn btn-primary" 
                      @click="openFileUploadModal"
                    >
                      <i class="fas fa-upload me-1"></i> Upload CSV File
                    </button>
                  </div>
                  <div v-if="csvFileName" class="csv-file-preview mt-3">
                    <div class="preview-header">
                      <h6><i class="fas fa-file-csv me-2"></i> CSV File Uploaded</h6>
                      <button type="button" class="btn-remove-preview" @click="clearCsvFile">
                        <i class="fas fa-times"></i>
                      </button>
                    </div>
                    <div class="preview-content">
                      <div class="file-name">{{ csvFileName }}</div>
                      <div class="file-details" v-if="csvColumns.length > 0">
                        <!-- <div class="file-columns">Columns detected: <span class="text-primary">{{ csvColumns.length }}</span></div> -->
                        <div class="file-help-text mt-2">
                          <small class="text-muted">Use the <code>{ }</code> button next to the message box to insert column values</small>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div v-if="errors.csv_file" class="text-danger mt-1">{{ errors.csv_file }}</div>
                </div>

                <!-- Contact list selection -->
                <div v-if="form.contact_type === 'list'">
                  <label for="contact_list" class="form-label fw-bold">Select Contact Group<span class="text-danger">*</span></label>
                  <MultiSelect 
                    id="contact_list" 
                    v-model="form.contact_list" 
                    :options="formattedContacts"
                    track-by="id"
                    label="label"
                    placeholder="Select contact groups"
                    :show-labels="false"
                    :clear-on-select="false"
                    :preserve-search="true"
                    :error="errors.contact_list"
                    @input="updateTagsCount"
                    ref="contactMultiSelect"
                  />
                  <small class="form-text text-muted">Click to search and select multiple contact groups</small>
                </div>
              </div>

              <!-- Message Box -->
              <div class="form-group mb-4">
                <label for="message" class="form-label fw-bold">Message<span class="text-danger">*</span></label>
                <div class="position-relative">
                  <div class="message-tools" v-if="form.contact_type === 'csv' && csvFilePath" @click.stop>
                    <div class="dropdown">
                      <button class="btn btn-sm btn-outline-secondary column-btn" type="button" @click="toggleColumnDropdown">
                        <span class="column-btn-icon">{ }</span>
                      </button>
                      <div class="column-dropdown" v-if="showColumnDropdown">
                        <div class="column-dropdown-header">Insert Column</div>
                        <!-- Debug output -->
                        <!-- <div class="debug-info" v-if="csvColumns && csvColumns.length > 0">
                          Available columns: {{ csvColumns.length }}
                        </div> -->
                        
                        <!-- Column list -->
                        <div class="column-list">
                          <button 
                            v-for="column in csvColumns" 
                            :key="column" 
                            class="column-item" 
                            @click.prevent="insertColumnAtCursor(column)"
                            type="button"
                          >
                            {{ column }}
                          </button>
                        </div>
                        <div v-if="!csvColumns || csvColumns.length === 0" class="no-columns">
                          No columns available
                        </div>
                      </div>
                    </div>
                  </div>
                  <textarea 
                    id="message" 
                    v-model="form.message" 
                    class="form-control" 
                    :class="{ 'is-invalid': errors.message, 'has-column-selector': form.contact_type === 'csv' && csvFilePath }"
                    rows="5" 
                    placeholder="Enter your message here"
                    @input="limitMessageLength"
                    ref="messageTextareaRef"
                  ></textarea>
                </div>
                <div class="d-flex justify-content-end mt-1">
                  <div class="message-counter" :class="{
                    'text-danger': remainingChars < 0,
                    'text-warning': remainingChars >= 0 && remainingChars < 10,
                    'over-limit': remainingChars < 0
                  }">
                    <span v-if="remainingChars >= 0">{{ remainingChars }} characters left</span>
                    <span v-else>{{ Math.abs(remainingChars) }} characters over limit</span>
                  </div>
                </div>
                <div v-if="errors.message" class="invalid-feedback d-block">{{ errors.message }}</div>
              </div>

              <!-- Schedule Message -->
              <div class="form-group mb-4">
                <div class="form-check mb-2">
                  <input 
                    class="form-check-input" 
                    type="checkbox" 
                    id="scheduled" 
                    v-model="form.scheduled"
                  />
                  <label class="form-check-label fw-bold" for="scheduled">Schedule SMS</label>
                </div>
                
                <!-- Date and Time Picker - Simple Native Approach -->
                <div v-if="form.scheduled" class="schedule-picker-container mt-3">
                  <div class="row">
                    <div class="col-md-6">
                      <label for="schedule_date" class="form-label">Select Date<span class="text-danger">*</span></label>
                      <input 
                        type="date" 
                        id="schedule_date" 
                        class="form-control" 
                        v-model="scheduleDateInput"
                        :min="getTodayDate()"
                        :class="{ 'is-invalid': errors.schedule_date }"
                      />
                    </div>
                    
                    <div class="col-md-6">
                      <label for="schedule_time" class="form-label">Select Time<span class="text-danger">*</span></label>
                      <input 
                        type="time" 
                        id="schedule_time" 
                        class="form-control" 
                        v-model="scheduleTimeInput"
                        :class="{ 'is-invalid': errors.schedule_date }"
                      />
                    </div>
                  </div>
                  
                  <div class="mt-2">
                    <span class="schedule-preview" v-if="scheduleDateInput && scheduleTimeInput">
                      <i class="fas fa-info-circle text-primary"></i>
                      Message will be sent on: <strong>{{ formatDisplayDate(scheduleDateInput, scheduleTimeInput) }}</strong>
                    </span>
                  </div>
                  
                  <small class="form-text text-muted mt-1">Select a future date and time for your SMS to be sent</small>
                  <div v-if="errors.schedule_date" class="invalid-feedback d-block">{{ errors.schedule_date }}</div>
                </div>
              </div>

              <div class="mt-4">
                <!-- <button type="button" class="btn btn-primary" @click="previewMessage">
                  <i class="fas fa-eye me-1"></i> PREVIEW SMS
                </button> -->
                <button type="button" class="btn btn-primary ms-2" @click="previewMessage" :disabled="isSubmitting">
                  <i class="fas fa-paper-plane me-1"></i> {{ form.scheduled ? 'PREVIEW & SCHEDULE SMS' : 'PREVIEW & SEND SMS' }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>

    <!-- CSV File Upload Modal -->
    <div class="modal fade" id="csvUploadModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content csv-upload-modal">
          <div class="modal-header">
            <h5 class="modal-title"><i class="fas fa-file-csv me-2"></i>Upload CSV File</h5>
            <button type="button" class="btn-close" @click="closeFileUploadModal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="upload-area mb-3">
              <div class="file-drop-zone" 
                :class="{'has-file': csvFile, 'is-loading': isValidatingCsv, 'is-dragging': isDragging}" 
                @click="!isValidatingCsv && triggerFileUpload()"
                @dragover.prevent="handleDragOver"
                @dragleave.prevent="handleDragLeave"
                @drop.prevent="handleDrop"
              >
                <div v-if="isValidatingCsv" class="upload-loading">
                  <div class="spinner-container">
                    <div class="spinner-border text-primary" role="status">
                      <span class="visually-hidden">Loading...</span>
                    </div>
                  </div>
                  <div class="upload-text mt-3">Validating and processing your CSV file...</div>
                  <div class="upload-subtext">This may take a moment</div>
                </div>
                <div v-else-if="!csvFile" class="upload-prompt">
                  <div class="upload-icon"><i class="fas fa-file-upload"></i></div>
                  <div class="upload-text">Drag and drop your CSV file here or click to browse</div>
                  <div class="upload-subtext">Supported file: .CSV</div>
                </div>
                <div v-else class="file-preview">
                  <div class="file-icon"><i class="fas fa-file-csv"></i></div>
                  <div class="file-info">
                    <div class="file-name">{{ csvFile.name }}</div>
                    <div class="file-size">{{ formatFileSize(csvFile.size) }}</div>
                  </div>
                  <button type="button" class="btn-remove-file" @click.stop="removeFile">
                    <i class="fas fa-times"></i>
                  </button>
                </div>
              </div>
              
              <!-- Validation Error Message -->
              <div v-if="csvValidationError" class="csv-validation-error mt-2">
                <i class="fas fa-exclamation-circle me-1"></i> {{ csvValidationError }}
              </div>
              <input 
                type="file" 
                id="csv_file" 
                ref="csvFileInput"
                class="form-control d-none" 
                accept=".csv"
                @change="handleFileUpload"
              />
            </div>
            <div class="csv-format-info">
              <h6><i class="fas fa-info-circle me-2"></i>&nbsp; CSV Format Requirements</h6>
              <ul>
                <li>File must be in CSV format</li>
                <li>File should have a header row</li>
                <li>Must include a column labeled 'phone', 'mobile', 'Telephonee' or 'contact'</li>
              
              </ul>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" @click="closeFileUploadModal" :disabled="isValidatingCsv">Cancel</button>
            <button type="button" class="btn btn-primary" @click="confirmFileUpload" :disabled="!csvFile || isValidatingCsv || csvValidationError">
              <i class="fas" :class="isValidatingCsv ? 'fa-spinner fa-spin' : 'fa-check me-1'"></i> {{ isValidatingCsv ? '' : 'Upload File' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- SMS Preview Modal -->
    <div class="modal fade" id="previewModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header bg-primary text-white">
            <h5 class="modal-title">SMS Preview</h5>
            <button type="button" class="btn-close" @click="closePreviewModal" aria-label="Close"></button>
          </div>
          <div class="modal-body p-2">
            <!-- Loading state -->
            <div v-if="isLoadingPreview" class="text-center p-4">
              <div class="spinner-border text-primary" role="status">
              </div>
            </div>
            
            <!-- Preview data -->
            <div v-else-if="preview" class="sms-preview-container">
              <!-- Top-level message preview card -->
              <div class="card mb-4 message-preview-card">
                <div class="card-header bg-primary text-white">
                  <h6 class="m-0"><i class="fas fa-comment-alt me-2"></i>Message Preview</h6>
                </div>
                <div class="card-body pb-0">
                  <!-- Show personalization info if available -->
                  <!-- <div v-if="preview.customizationInfo" class="personalization-notice mb-3">
                    <span class="badge bg-info"><i class="fas fa-info-circle me-1"></i> Personalized</span>
                    <small class="ms-2 text-muted">{{ preview.customizationInfo }}</small>
                  </div> -->
                  
                  <!-- Message bubble -->
                  <div class="mb-4">
                    <div>
                      {{ preview.personalizedMessage || preview.message || form.message }}
                    </div>
                    <div>
                     
                    </div>
                  </div>
                </div>
              </div>
              
              <!-- Contact statistics in a card that matches the screenshot -->
              <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                  <h6 class="m-0"><i class="fas fa-address-book me-2"></i>Contact Statistics</h6>
                </div>
                <div class="card-body p-0">
                  <div class="stats-grid">
                    <div class="stat-box">
                      <div class="stat-label">Total Contacts</div>
                      <div class="stat-value total">{{ preview.totalContacts || 0 }}</div>
                    </div>
                    <div class="stat-box">
                      <div class="stat-label">Valid Contacts</div>
                      <div class="stat-value valid">{{ preview.validContacts || 0 }}</div>
                    </div>
                    <div class="stat-box">
                      <div class="stat-label">Invalid Contacts</div>
                      <div class="stat-value invalid">{{ preview.invalidContacts || 0 }}</div>
                    </div>
                    <div class="stat-box">
                      <div class="stat-label">Message Characters</div>
                      <div class="stat-value">{{ preview.messageTotalChars || 0 }}</div>
                    </div>
                  </div>
                </div>
              </div>
              
              <!-- Schedule information in a nicer format -->
              <div v-if="form.scheduled" class="card schedule-card">
                <div class="card-body">
                  <div class="schedule-icon">
                    <i class="fas fa-calendar-alt"></i>
                  </div>
                  <div class="schedule-info">
                    <div class="schedule-label">Scheduled Delivery</div>
                    <div class="schedule-date">{{ formatDisplayDate(scheduleDateInput, scheduleTimeInput) }}</div>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Error state -->
            <div v-else class="alert alert-danger">
              <i class="fas fa-exclamation-triangle me-2"></i>
              Error loading preview. Please try again.
            </div>
          </div>
          
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="closePreviewModal">Close</button>
            <button 
              type="button" 
              class="btn btn-success" 
              @click="confirmSend" 
              :disabled="isLoadingPreview || (preview && preview.validContacts === 0)"
            >
              {{ form.scheduled ? 'Schedule SMS' : 'Send SMS' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
// No need for external datepicker components
// Import Bootstrap if needed (assume it might already be globally imported)
let Modal;


// Props from controller
const props = defineProps({
  contacts: Array
});

// Form state
const form = useForm({
  text_title: '',
  contact_type: 'manual', // Default to manual to avoid empty state
  recepient_contacts: '',
  contact_list: [],
  message: '',
  scheduled: false,
  schedule_date: null,
  csv_file: null,
  csv_file_path: '',
  csv_columns: []
});

// Additional reactive state
const csvFile = ref(null);
const csvFileName = ref('');
const csvFilePath = ref('');
const csvColumns = ref([]);
const csvValidationError = ref('');
const isValidatingCsv = ref(false); // Loading state for CSV validation
const isSubmitting = ref(false);
const errors = ref({});
const preview = ref(null);
const isLoadingPreview = ref(false);
const messageLength = ref(0);
const csvFileInput = ref(null);
const messageTextareaRef = ref(null);
const showColumnDropdown = ref(false);
const scheduleDateInput = ref('');
const scheduleTimeInput = ref('');
const remainingChars = computed(() => 160 - (form.message ? form.message.length : 0));

// Get today's date in YYYY-MM-DD format for min date attribute
const getTodayDate = () => {
  const today = new Date();
  const year = today.getFullYear();
  const month = (today.getMonth() + 1).toString().padStart(2, '0');
  const day = today.getDate().toString().padStart(2, '0');
  return `${year}-${month}-${day}`;
};

// Format the display date for the preview
const formatDisplayDate = (dateStr, timeStr) => {
  if (!dateStr || !timeStr) return '';
  
  try {
    // Parse the date string (YYYY-MM-DD)
    const [year, month, day] = dateStr.split('-');
    
    // Parse the time string (HH:MM)
    const [hours24, minutes] = timeStr.split(':');
    let hours = parseInt(hours24);
    const ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12;
    hours = hours ? hours : 12; // the hour '0' should be '12'
    
    // Format as DD/MM/YYYY hh:mm AM/PM
    return `${day}/${month}/${year} ${hours}:${minutes} ${ampm}`;
  } catch (e) {
    console.error('Error formatting date display:', e);
    return '';
  }
};

// Combine date and time inputs into a single Date object before form submission
const updateScheduleDate = () => {
  if (scheduleDateInput.value && scheduleTimeInput.value) {
    // Create a new Date object from the date and time inputs
    const [year, month, day] = scheduleDateInput.value.split('-');
    const [hours, minutes] = scheduleTimeInput.value.split(':');
    
    const scheduleDate = new Date(
      parseInt(year),
      parseInt(month) - 1, // Month is 0-based in JavaScript
      parseInt(day),
      parseInt(hours),
      parseInt(minutes)
    );
    
    // Update the form's schedule_date
    form.schedule_date = scheduleDate;
  }
};

// Toggle the column dropdown visibility
const toggleColumnDropdown = () => {
  showColumnDropdown.value = !showColumnDropdown.value;
  console.log('Column dropdown toggled:', showColumnDropdown.value, 'Available columns:', csvColumns.value);
};

// Close dropdown when clicking outside
onMounted(() => {
  document.addEventListener('click', (event) => {
    // Only close if click is outside dropdown area
    const columnSelector = document.querySelector('.dropdown');
    if (showColumnDropdown.value && columnSelector && !columnSelector.contains(event.target)) {
      showColumnDropdown.value = false;
    }
  });
});

// Format contacts for MultiSelect component
const formattedContacts = computed(() => {
  return props.contacts.map(contact => ({
    id: contact.id,
    label: contact.title,
    count: contact.contact_lists_count || 0
  }));
});

// Reference to the MultiSelect component
const contactMultiSelect = ref(null);

// Update the selected count display in the MultiSelect component
const updateTagsCount = () => {
  setTimeout(() => {
    // Get the multiselect container
    const multiSelect = document.querySelector('#contact_list');
    if (multiSelect && form.contact_list && form.contact_list.length > 0) {
      // Find or create the count element
      let countElement = multiSelect.querySelector('.selected-count');
      if (!countElement) {
        countElement = document.createElement('span');
        countElement.className = 'selected-count';
        const tagsContainer = multiSelect.querySelector('.multiselect__tags');
        if (tagsContainer) {
          tagsContainer.appendChild(countElement);
        }
      }
      
      // Update the count display
      countElement.textContent = `${form.contact_list.length} selected`;
      
      // Make sure it's visible
      countElement.style.display = 'block';
    } else {
      // Remove count element if no selections
      const countElement = document.querySelector('.selected-count');
      if (countElement) {
        countElement.style.display = 'none';
      }
    }
  }, 50);
};

// Initialize the selected count when component is mounted
onMounted(() => {
  if (form.contact_list && form.contact_list.length > 0) {
    updateTagsCount();
  }
});

// CSV file handling
const handleFileUpload = async (event) => {
  const files = event.target.files;
  
  if (files.length > 0) {
    const file = files[0];
    
    // Check if file is CSV
    if (file.type === 'text/csv' || file.name.endsWith('.csv')) {
      // Set loading state
      isValidatingCsv.value = true;
      csvValidationError.value = '';
      
      try {
        // Create a FormData object to send the file
        const formData = new FormData();
        formData.append('csv_file', file);
        
        // Make API request to validate CSV
        const response = await axios.post(route('csv.validate'), formData, {
          headers: {
            'Content-Type': 'multipart/form-data'
          }
        });
        
        // Handle successful validation
        if (response.data.status === 'success') {
          // Store the raw data from response for debugging
          console.log('Raw response data:', response.data);
          
          // Store CSV file details
          csvFile.value = file;
          csvFileName.value = response.data.file_name;
          csvFilePath.value = response.data.file_path;
          
          // Process columns - force to array if needed
          const columnData = response.data.columns;
          if (Array.isArray(columnData) && columnData.length > 0) {
            // Use directly if already an array
            csvColumns.value = [...columnData];
            console.log('CSV Columns (array format):', csvColumns.value);
          } else if (columnData && typeof columnData === 'object') {
            // Convert object to array if needed
            csvColumns.value = Object.keys(columnData);
            console.log('CSV Columns (object keys):', csvColumns.value);
          } else {
            // Fallback for other formats
            console.error('Columns in unexpected format:', columnData);
            csvColumns.value = [];
          }
          
          // Update form data
          form.csv_columns = [...csvColumns.value];
          form.csv_file_path = response.data.file_path;
          form.csv_file = file;
          form.contact_type = 'csv';
          
          // Close the modal since validation succeeded
          closeFileUploadModal();
          
        } else {
          // Handle validation failure
          csvValidationError.value = response.data.message || 'CSV validation failed';
          csvFile.value = null;
          // Clear file input
          if (csvFileInput.value) {
            csvFileInput.value.value = '';
          }
        }
      } catch (error) {
        console.error('CSV validation error:', error);
        csvValidationError.value = error.response?.data?.message || 'An error occurred during CSV validation';
        
        // Clear the file on error
        csvFile.value = null;
        if (document.getElementById('csv_file')) {
          document.getElementById('csv_file').value = '';
        }
      } finally {
        // Clear loading state
        isValidatingCsv.value = false;
      }
    } else {
      // Invalid file type
      csvValidationError.value = 'Please select a valid CSV file';
      event.target.value = ''; // Clear the input
    }
  }
};

// Format file size for display
const formatFileSize = (bytes) => {
  if (bytes === 0) return '0 Bytes';
  const k = 1024;
  const sizes = ['Bytes', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

// Drag and drop related handlers
const isDragging = ref(false);

// Handle dragover event - happens when file is dragged over drop zone
const handleDragOver = (event) => {
  isDragging.value = true;
  event.dataTransfer.dropEffect = 'copy';
};

// Handle dragleave event - happens when file is dragged out of drop zone
const handleDragLeave = () => {
  isDragging.value = false;
};

// Handle drop event - happens when file is dropped in drop zone
const handleDrop = (event) => {
  isDragging.value = false;
  if (isValidatingCsv.value) return; // Don't allow drops while validating
  
  const files = event.dataTransfer.files;
  if (files.length > 0) {
    // Get the first file only
    const droppedFile = files[0];
    
    // Update the file input element to reflect the dropped file
    if (csvFileInput.value) {
      // Create a new DataTransfer object to set the files property
      const dataTransfer = new DataTransfer();
      dataTransfer.items.add(droppedFile);
      csvFileInput.value.files = dataTransfer.files;
      
      // Trigger the change event handler
      handleFileUpload({ target: { files: dataTransfer.files } });
    }
  }
};

// Trigger file input click when clicking on the drop zone
const triggerFileUpload = () => {
  // Access the file input directly via ref instead of by ID
  if (csvFileInput.value) {
    csvFileInput.value.click();
  }
};

// Remove selected file in the modal
const removeFile = (event) => {
  event.stopPropagation(); // Prevent triggering the file input
  csvFile.value = null;
  csvValidationError.value = '';
  
  // Clear the file input using the Vue ref
  if (csvFileInput.value) {
    csvFileInput.value.value = '';
  }
};

// Clear the CSV file from the main form
const clearCsvFile = () => {
  // Clear all CSV-related data
  csvFile.value = null;
  csvFileName.value = '';
  csvFilePath.value = '';
  csvColumns.value = [];
  csvValidationError.value = '';
  form.csv_file = null;
  form.csv_file_path = '';
  form.csv_columns = [];
  
  // Clear the file input
  if (csvFileInput.value) {
    csvFileInput.value.value = '';
  }
};

// Insert column placeholder at cursor position in message textarea
const insertColumnAtCursor = (columnName) => {
  const messageTextarea = messageTextareaRef.value;
  
  if (messageTextarea) {
    // Get cursor position
    const start = messageTextarea.selectionStart;
    const end = messageTextarea.selectionEnd;
    
    // Create the column placeholder
    const columnPlaceholder = `{${columnName}}`;
    
    // Insert the placeholder at cursor position
    const before = form.message.substring(0, start);
    const after = form.message.substring(end);
    
    // Update form message with placeholder inserted
    form.message = before + columnPlaceholder + after;
    
    // Set cursor position after the inserted placeholder
    setTimeout(() => {
      messageTextarea.focus();
      messageTextarea.setSelectionRange(start + columnPlaceholder.length, start + columnPlaceholder.length);
    }, 0);
    
    // Close the dropdown after insertion
    showColumnDropdown.value = false;
  }
};

const openFileUploadModal = () => {
  try {
    // Reset file input and state first
    csvFile.value = null;
    csvFileName.value = '';
    csvFilePath.value = '';
    csvColumns.value = [];
    csvValidationError.value = '';
    
    // Clear the file input using the Vue ref
    if (csvFileInput.value) {
      csvFileInput.value.value = '';
    }
    
    // Check if bootstrap is defined globally
    if (window.bootstrap) {
      const modal = new window.bootstrap.Modal(document.getElementById('csvUploadModal'));
      modal.show();
    } else if (Modal) {
      // Use the local reference if it's been initialized
      const modal = new Modal(document.getElementById('csvUploadModal'));
      modal.show();
    } else {
      // Fallback - just show the element using plain JS
      const modalEl = document.getElementById('csvUploadModal');
      modalEl.style.display = 'block';
      modalEl.classList.add('show');
      document.body.classList.add('modal-open');
      // Add backdrop
      const backdrop = document.createElement('div');
      backdrop.className = 'modal-backdrop fade show';
      document.body.appendChild(backdrop);
    }
    
    // No automatic file dialog trigger when opening the modal
    // The user will click on the dropzone to trigger the file selection
  } catch (e) {
    console.error('Error opening modal:', e);
    alert('There was an error opening the file upload dialog');
  }
};

// Close modal function
const closeFileUploadModal = () => {
  try {
    if (window.bootstrap) {
      const modalInstance = window.bootstrap.Modal.getInstance(document.getElementById('csvUploadModal'));
      if (modalInstance) modalInstance.hide();
    } else {
      // Fallback - just hide the element using plain JS
      const modalEl = document.getElementById('csvUploadModal');
      modalEl.style.display = 'none';
      modalEl.classList.remove('show');
      document.body.classList.remove('modal-open');
      // Remove backdrop
      const backdrop = document.querySelector('.modal-backdrop');
      if (backdrop) backdrop.parentNode.removeChild(backdrop);
    }
  } catch (e) {
    console.error('Error closing modal:', e);
  }
};

const confirmFileUpload = () => {
  if (csvFile.value) {
    // Debug the column data
    console.log('CSV file confirmed. Columns:', csvColumns.value);
    
    // Update form data
    form.csv_file = csvFile.value;
    form.csv_file_path = csvFilePath.value;
    form.csv_columns = csvColumns.value;
    form.contact_type = 'csv';
    
    // Close modal
    closeFileUploadModal();
  }
};

// Message length tracking - no hard limit
const limitMessageLength = () => {
  // Just update the message length for the counter display
  // No character limit enforced - user can type beyond 160 chars
  messageLength.value = form.message ? form.message.length : 0;
};

// Validate form before preview
const validateForm = () => {
  let isValid = true;
  errors.value = {};
  
  // Debug current form state with correct field names
  console.log('Validating - Contact Type:', form.contact_type);
  console.log('Manual contacts:', form.recepient_contacts);
  console.log('Contact list:', form.contact_list);
  console.log('CSV file path:', form.csv_file_path);
  
  // Required field validation
  if (!form.text_title) {
    errors.value.text_title = 'The title field is required';
    isValid = false;
  }
  
  if (!form.message) {
    errors.value.message = 'The message field is required';
    isValid = false;
  }
  
  if (!form.contact_type) {
    errors.value.contact_type = 'The contact type field is required';
    isValid = false;
  }
  
  // Contact type specific validation
  if (form.contact_type === 'manual') {
    if (!form.recepient_contacts || form.recepient_contacts.trim() === '') {
      errors.value.recepient_contacts = 'Please enter comma-separated contact numbers';
      isValid = false;
    }
  }
  
  if (form.contact_type === 'contact_list') {
    if (!form.contact_list || !Array.isArray(form.contact_list) || form.contact_list.length === 0) {
      errors.value.contact_list = 'Please select at least one contact group';
      isValid = false;
    }
  }
  
  if (form.contact_type === 'csv') {
    if (!form.csv_file_path) {
      errors.value.csv_file = 'Please upload a CSV file';
      isValid = false;
    }
  }
  
  // Schedule validation
  if (form.scheduled && (!scheduleDateInput.value || !scheduleTimeInput.value)) {
    errors.value.schedule_date = 'Please select both date and time';
    isValid = false;
  }
  
  console.log('Validation result:', isValid);
  console.log('Validation errors:', errors.value);
  return isValid;
};

// Preview functionality
const previewMessage = async () => {
  // Validate form first
  if (!validateForm()) {
    // Scroll to the first error
    const firstErrorElement = document.querySelector('.is-invalid, .invalid-feedback');
    if (firstErrorElement) {
      firstErrorElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    return;
  }
  
  try {
    // Set loading state
    isLoadingPreview.value = true;
    preview.value = null;
    
    // If using scheduled SMS, update the date object
    if (form.scheduled) {
      updateScheduleDate();
    }
    
    // Prepare form data for server
    const formData = new FormData();
    
    // Show debug info
    console.log('Form state before sending:', {
      text_title: form.text_title,
      message: form.message,
      contact_type: form.contact_type,
      recepient_contacts: form.recepient_contacts,
      contact_list: form.contact_list,
      csv_file_path: form.csv_file_path
    });
    
    // CRITICAL: Determine correct contact_type value for backend
    let backendContactType = form.contact_type;
    if (form.contact_type === 'contact_list') {
      backendContactType = 'list';  // Backend expects 'list', not 'contact_list'
    }
    
    // Always add common fields first
    formData.append('text_title', form.text_title);
    formData.append('message', form.message);
    formData.append('contact_type', backendContactType);
    
    // Handle each contact type specifically
    if (backendContactType === 'manual') {
      // Manual (comma-separated) contacts
      formData.append('recepient_contacts', form.recepient_contacts || '');
    } 
    else if (backendContactType === 'csv') {
      // CSV file upload
      formData.append('csv_file_path', form.csv_file_path || '');
      if (form.csv_file) {
        formData.append('csv_file', form.csv_file);
      }
    } 
    else if (backendContactType === 'list') {
      // Contact groups/lists
      if (Array.isArray(form.contact_list) && form.contact_list.length > 0) {
        // Handle array of IDs
        form.contact_list.forEach(id => {
          formData.append('contact_list[]', id);
        });
      }
    }
    
    // Append other common form fields
    formData.append('message', form.message);
    formData.append('text_title', form.text_title);
    
    // Add schedule data if needed
    if (form.scheduled) {
      // Make sure to update the schedule_date before preview
      updateScheduleDate();
      formData.append('scheduled', form.scheduled);
      if (form.schedule_date) {
        formData.append('schedule_date', form.schedule_date.toISOString());
      }
    }
    
    // Setup preview modal and event listeners
    const setupPreviewModal = () => {
      // Get modal element
      const modalEl = document.getElementById('previewModal');
      
      // Initialize Bootstrap modal if available
      if (window.bootstrap) {
        // Check if modal instance exists already
        let modalInstance = window.bootstrap.Modal.getInstance(modalEl);
        
        // If not, create a new instance
        if (!modalInstance) {
          modalInstance = new window.bootstrap.Modal(modalEl, {
            backdrop: 'static',  // Prevent closing when clicking outside
            keyboard: false      // Prevent closing with keyboard
          });
        }
        
        // Show the modal
        modalInstance.show();
      } else {
        // Fallback to plain JavaScript
        modalEl.style.display = 'block';
        modalEl.classList.add('show');
        document.body.classList.add('modal-open');
        
        // Add backdrop
        const backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop fade show';
        document.body.appendChild(backdrop);
      }
    };
    
    // Show modal first
    setupPreviewModal();
    
    // Call preview endpoint
    const response = await axios.post(route('sms.preview'), formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    });
    
    console.log('Raw preview response:', response.data);
    
    // Handle the response data correctly
    if (response.data.status === 'success' && response.data.preview) {
      // Debug output before processing
      console.log('Raw preview data from backend:', response.data.preview);
      
      // Backend returns nested preview data
      preview.value = response.data.preview;
      
      // Log what we received for personalized message
      console.log('Personalized message received:', preview.value.personalizedMessage);
      
      // Ensure required fields exist with proper defaults
      preview.value.validContacts = preview.value.validContacts || 0;
      preview.value.invalidContacts = preview.value.invalidContacts || 0;
      preview.value.totalContacts = preview.value.totalContacts || preview.value.contacts_count || 0;
      preview.value.messageTotalChars = preview.value.messageTotalChars || form.message.length;
      
      // Make sure personalized message is displayed (highest priority)
      if (!preview.value.personalizedMessage && preview.value.message) {
        preview.value.personalizedMessage = preview.value.message;
      } else if (!preview.value.personalizedMessage) {
        preview.value.personalizedMessage = form.message;
      }
    } else if (response.data) {
      // Direct response format
      // Store the raw response first
      preview.value = response.data;
      
      console.log('Raw preview data from server:', response.data);
      
      // Ensure required fields exist with proper fallbacks
      preview.value.validContacts = response.data.validContacts || 0;
      preview.value.invalidContacts = response.data.invalidContacts || 0;
      preview.value.totalContacts = response.data.totalContacts || response.data.contacts_count || 0;
      preview.value.messageTotalChars = response.data.messageTotalChars || form.message.length;
      
      // Properly handle personalized message
      preview.value.personalizedMessage = response.data.personalizedMessage || form.message;
      preview.value.originalMessage = response.data.originalMessage || form.message;
      
      // Add customization info if CSV method is used and we have a personalized message
      if (form.contact_type === 'csv' && response.data.customizationInfo) {
        preview.value.customizationInfo = response.data.customizationInfo;
      } else if (form.contact_type === 'csv' && response.data.personalizedMessage && response.data.personalizedMessage !== form.message) {
        preview.value.customizationInfo = "Message shown is personalized using the first row of your CSV data. Each recipient will receive their own customized message.";
      }
    }
    
    console.log('Processed preview data:', preview.value);
  } catch (error) {
    if (error.response && error.response.data && error.response.data.errors) {
      errors.value = error.response.data.errors;
      console.error('Validation errors:', error.response.data.errors);
    } else if (error.response && error.response.data && error.response.data.message) {
      // Show the error message from the server
      alert('Error: ' + error.response.data.message);
      console.error('Server error:', error.response.data.message);
    } else {
      console.error('Error previewing message:', error);
      alert('An error occurred while previewing your message. Please try again.');
    }
  } finally {
    // Always clear loading state when done
    isLoadingPreview.value = false;
  }
};

// Form submission
const submitForm = () => {
  // Reset errors
  errors.value = {};
  
  // Validate date and time for scheduled SMS
  if (form.scheduled) {
    if (!scheduleDateInput.value || !scheduleTimeInput.value) {
      errors.value.schedule_date = 'Please select both date and time';
      return;
    }
    
    // Update the form's schedule_date before submission by combining date and time
    updateScheduleDate();
  } else {
    // Don't include schedule_date if not scheduled
    form.schedule_date = null;
  }
  
  isSubmitting.value = true;

  // Submit form
  form.post(route('sms.store'), {
    forceFormData: true,
    onSuccess: () => {
      // Reset form
      form.reset();
      csvFileName.value = '';
      scheduleDateInput.value = '';
      scheduleTimeInput.value = '';
      isSubmitting.value = false;
    },
    onError: (formErrors) => {
      errors.value = formErrors;
      isSubmitting.value = false;
    }
  });
};

// Close the preview modal
const closePreviewModal = () => {
  // Close modal safely using Bootstrap or fallback
  try {
    if (window.bootstrap) {
      const modalInstance = window.bootstrap.Modal.getInstance(document.getElementById('previewModal'));
      if (modalInstance) modalInstance.hide();
    } else {
      // Fallback - just hide the element using plain JS
      const modalEl = document.getElementById('previewModal');
      modalEl.style.display = 'none';
      modalEl.classList.remove('show');
      document.body.classList.remove('modal-open');
      // Remove backdrop
      const backdrop = document.querySelector('.modal-backdrop');
      if (backdrop) backdrop.parentNode.removeChild(backdrop);
    }
  } catch (e) {
    console.error('Error closing preview modal:', e);
  }
};

// Confirm send from preview modal
const confirmSend = () => {
  // Close the modal first
  closePreviewModal();
  
  // Submit form
  submitForm();
};

// Format date for display
const formatDate = (dateString) => {
  if (!dateString) return '';
  const date = new Date(dateString);
  return date.toLocaleString();
};

onMounted(() => {
  // Initialize default datetime for scheduling (current time + 1 hour)
  const now = new Date();
  now.setHours(now.getHours() + 1);
  form.schedule_date = now.toISOString().slice(0, 16);

  // Check for Bootstrap and set up Modal reference
  if (window.bootstrap) {
    Modal = window.bootstrap.Modal;
  } else {
    // Log the absence of Bootstrap
    console.warn('Bootstrap not found. Using fallback modal handling.');
  }
});
</script>

<style scoped>
/* General Form Enhancement */
.card {
  border: none;
  box-shadow: 0 0 20px rgba(0, 0, 0, 0.08);
  border-radius: 8px;
  overflow: hidden;
  transition: all 0.3s ease;
}

.card:hover {
  box-shadow: 0 0 25px rgba(0, 0, 0, 0.12);
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

.card-title::before {
  content: '\f7cd';
  font-family: 'Font Awesome 5 Free';
  font-weight: 900;
  margin-right: 10px;
  font-size: 1.1em;
}

.card-body {
  padding: 2rem 1.5rem;
  background-color: #fff;
}

/* Form controls styling */
.form-control, .form-select, textarea {
  border: 1px solid #d1d5db;
  border-radius: 6px;
  padding: 0.6rem 1rem;
  transition: all 0.3s ease;
  box-shadow: none;
  background-color: #f9fafb;
}

.form-control:focus, .form-select:focus, textarea:focus {
  border-color: #007bff;
  box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.15);
  background-color: #fff;
}

.form-control::placeholder {
  color: #9ca3af;
  font-style: italic;
}

.form-label {
  color: #374151;
  margin-bottom: 0.5rem;
  font-size: 0.95rem;
}

.form-group {
  margin-bottom: 1.5rem;
  position: relative;
}

.form-text {
  color: #6b7280;
  font-size: 0.85rem;
  margin-top: 0.5rem;
}

/* Input Group Styling */
.input-group {
  border-radius: 6px;
  overflow: hidden;
}

.input-group .btn {
  padding: 0.6rem 1rem;
  display: flex;
  align-items: center;
}

.input-group .btn i {
  margin-right: 6px;
}

/* Form Check Styling */
.form-check-input[type="checkbox"] {
  width: 1.25rem;
  height: 1.25rem;
  margin-top: 0.15rem;
  margin-right: 0.5rem;
  cursor: pointer;
  appearance: none;
  border: 2px solid #d1d5db;
  border-radius: 4px;
  transition: all 0.3s ease;
}

.form-check-input[type="checkbox"]:checked {
  background-color: #007bff;
  border-color: #007bff;
  position: relative;
}

.form-check-input[type="checkbox"]:checked::after {
  content: '\f00c';
  font-family: 'Font Awesome 5 Free';
  font-weight: 900;
  font-size: 0.75rem;
  color: white;
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
}

/* Fancy Radio Buttons */
.form-check-inline {
  margin-right: 1.5rem;
  display: inline-flex;
  align-items: center;
  background-color: #f9fafb;
  padding: 0.5rem 1rem;
  border-radius: 6px;
  transition: all 0.2s ease;
  border: 1px solid transparent;
}

.form-check-inline:hover {
  background-color: #edf2f7;
  border-color: #e5e7eb;
}

.form-check-input[type="radio"] {
  position: relative;
  width: 1.5rem;
  height: 1.5rem;
  margin-top: 0;
  margin-right: 0.5rem;
  cursor: pointer;
  appearance: none;
  border: 2px solid #d1d5db;
  border-radius: 50%;
  transition: all 0.3s ease;
}

.form-check-input[type="radio"]:checked {
  border-color: #007bff;
  background-color: #fff;
  box-shadow: 0 0 5px rgba(0, 123, 255, 0.3);
}

.form-check-input[type="radio"]:checked::after {
  content: '';
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 0.75rem;
  height: 0.75rem;
  border-radius: 50%;
  background-color: #007bff;
  transition: all 0.2s ease;
}

.form-check-input[type="radio"]:hover {
  border-color: #007bff;
}

.form-check-label {
  font-weight: 500;
  cursor: pointer;
  transition: color 0.2s ease;
}

.form-check-input[type="radio"]:checked + .form-check-label {
  color: #007bff;
}

/* Validation Feedback */
.invalid-feedback {
  display: block;
  font-size: 0.85rem;
  margin-top: 0.5rem;
  color: #ef4444;
}

.is-invalid {
  border-color: #ef4444 !important;
  background-image: none !important;
}

.is-invalid:focus {
  box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.15) !important;
}

/* Button Styling */
.btn-primary {
  background: linear-gradient(135deg, #0d6efd, #0a58ca);
  border: none;
  padding: 0.65rem 1.5rem;
  font-weight: 600;
  letter-spacing: 0.5px;
  border-radius: 6px;
  transition: all 0.3s ease;
  box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
}

.btn-primary:hover, .btn-primary:focus {
  background: linear-gradient(135deg, #0b5ed7, #0a4fab);
  transform: translateY(-1px);
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
}

.btn-primary:active {
  transform: translateY(0);
  box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
}

/* Message Box Counter */
.message-counter {
  position: absolute;
  bottom: 8px;
  right: 10px;
  font-size: 0.8rem;
  color: #6b7280;
  background-color: rgba(255, 255, 255, 0.9);
  padding: 0.2rem 0.5rem;
  border-radius: 4px;
}

/* MultiSelect Styling */
.multiselect {
  min-height: 45px;
  margin-bottom: 0.25rem;
}

.multiselect__placeholder {
  padding-top: 10px;
}

.multiselect__option--highlight {
  background: #f0f9ff;
  color: #0d6efd;
}

.multiselect__option--selected {
  background: #f8f9fa;
  color: #212529;
  font-weight: 500;
}

.multiselect__option--selected.multiselect__option--highlight {
  background: #e6f2ff;
  color: #0d6efd;
}

.multiselect__option--selected::before {
  content: "";
  display: inline-block;
  width: 16px;
  height: 16px;
  margin-right: 8px;
  background: #0d6efd url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20'%3e%3cpath fill='none' stroke='%23fff' stroke-linecap='round' stroke-linejoin='round' stroke-width='3' d='M6 10l3 3l6-6'/%3e%3c/svg%3e");
  border: 1px solid #0d6efd;
  border-radius: 3px;
}

.multiselect__option::before {
  content: "";
  display: inline-block;
  width: 16px;
  height: 16px;
  margin-right: 8px;
  background: #fff;
  border: 1px solid #ced4da;
  border-radius: 3px;
}

.multiselect__tag {
  background: #e6f2ff;
  color: #0d6efd;
  border-radius: 4px;
  padding: 2px 24px 2px 8px;
  margin-right: 5px;
  margin-bottom: 3px;
}

.multiselect__tag-icon {
  background: #dc3545;
  border-radius: 50%;
  color: white !important;
  font-weight: 700;
  width: 20px;
  height: 20px;
  right: 0;
  margin-right: 2px;
}

.multiselect__tag-icon:after {
  color: white !important;
  font-size: 14px;
  line-height: 20px;
}

.multiselect__tag-icon:focus, 
.multiselect__tag-icon:hover {
  background: #bb2d3b;
}

.multiselect__tags-wrap::after {
  content: attr(data-count) " selected";
  display: inline-block;
  margin-left: 5px;
  color: #0d6efd;
  font-size: 0.8rem;
  font-weight: 500;
}

.multiselect__select {
  height: 40px;
}

/* Date Picker Styling */
.schedule-picker-container {
  max-width: 400px;
  position: relative;
}

.date-time-picker {
  position: relative;
}

/* Native Date/Time Picker Styling */
.schedule-picker-container {
  background-color: #f8f9fa;
  border-radius: 8px;
  padding: 15px;
  margin-top: 10px;
  border: 1px solid #dee2e6;
}

.schedule-preview {
  display: inline-block;
  background-color: #e9f7fe;
  padding: 8px 12px;
  border-radius: 6px;
  border-left: 3px solid #0d6efd;
  font-size: 0.9rem;
  color: #495057;
}

/* Style the native date and time inputs */
.schedule-picker-container input[type="date"],
.schedule-picker-container input[type="time"] {
  font-family: inherit;
  font-size: 1rem;
  color: #495057;
  padding: 0.5rem 0.75rem;
  cursor: pointer;
}

/* Add more specific styling */
.schedule-picker-container input[type="date"]::-webkit-calendar-picker-indicator,
.schedule-picker-container input[type="time"]::-webkit-calendar-picker-indicator {
  color: #0d6efd;
  cursor: pointer;
  opacity: 0.6;
}

.schedule-picker-container input[type="date"]::-webkit-calendar-picker-indicator:hover,
.schedule-picker-container input[type="time"]::-webkit-calendar-picker-indicator:hover {
  opacity: 1;
}

/* Override the input group */
.schedule-picker-container .input-group-text {
  background-color: #f8f9fa;
  color: #0d6efd;
  border-color: #ced4da;
}

/* Add spacing between date and time on mobile */
@media (max-width: 767.98px) {
  .schedule-picker-container .col-md-6:first-child {
    margin-bottom: 1rem;
  }
}

/* Override VueDatepicker styles to match AdminLTE */
:deep(.dp__theme_light) {
  --dp-background-color: #fff;
  --dp-text-color: #212529;
  --dp-hover-color: #f8f9fa;
  --dp-hover-text-color: #0d6efd;
  --dp-hover-icon-color: #0d6efd;
  --dp-primary-color: #0d6efd;
  --dp-primary-text-color: #fff;
  --dp-secondary-color: #e9ecef;
  --dp-border-color: #ced4da;
  --dp-menu-border-color: #ced4da;
  --dp-border-color-hover: #adb5bd;
  --dp-disabled-color: #f8f9fa;
  --dp-disabled-text-color: #6c757d;
  --dp-icon-color: #6c757d;
  --dp-danger-color: #dc3545;
}

/* Date picker button styling */
.schedule-input-container .input-group .btn {
  border-color: #ced4da;
  color: #6c757d;
}

.schedule-input-container .input-group .btn:hover {
  background-color: #0d6efd;
  border-color: #0d6efd;
  color: white;
}

/* Selected count styling */
.selected-count {
  position: absolute;
  top: 4px;
  right: 30px;
  background-color: #0d6efd;
  color: white;
  font-size: 0.8rem;
  padding: 1px 8px;
  border-radius: 10px;
  font-weight: 600;
}

:deep(.multiselect-container) {
  margin-bottom: 0.5rem;
}

:deep(.multiselect-container .form-select) {
  border: 1px solid #d1d5db !important;
  border-radius: 6px;
  outline: none;
  box-shadow: none;
  background-color: #f9fafb;
  transition: all 0.3s ease;
}

:deep(.multiselect-container .form-select:hover) {
  border-color: #b0b7c0 !important;
}

:deep(.multiselect-container .form-select:focus-within) {
  border-color: #007bff !important;
  box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.15);
  background-color: #fff;
}

:deep(.selected-tag) {
  background-color: #e6f2ff;
  border: 1px solid #b3d9ff;
  border-radius: 4px;
  padding: 0.2rem 0.6rem;
  margin: 0.15rem;
  font-size: 0.875rem;
  display: inline-flex;
  align-items: center;
  transition: all 0.2s ease;
}

:deep(.selected-tag:hover) {
  background-color: #d9ebff;
}

:deep(.btn-close) {
  font-size: 0.7rem;
  padding: 0.15rem;
  margin-left: 0.5rem;
  opacity: 0.6;
}

:deep(.btn-close:hover) {
  opacity: 1;
}

/* CSV Upload Modal Styling */
.csv-upload-modal .modal-header {
  background: linear-gradient(135deg, #0866c6, #0659ab);
  color: white;
  border-bottom: none;
}

.csv-upload-modal .modal-title {
  font-weight: 600;
  display: flex;
  align-items: center;
}

.csv-upload-modal .modal-title i {
  font-size: 1.1em;
}

.csv-upload-modal .btn-close {
  color: white;
  opacity: 0.8;
  filter: brightness(5);
}

.csv-upload-modal .modal-content {
  border: none;
  border-radius: 8px;
  overflow: hidden;
  box-shadow: 0 5px 25px rgba(0,0,0,0.1);
}

.csv-upload-modal .modal-body {
  padding: 1.5rem;
}

.file-drop-zone {
  border: 2px dashed #d1d5db;
  border-radius: 8px;
  padding: 2rem;
  text-align: center;
  cursor: pointer;
  transition: all 0.3s ease;
  background-color: #f9fafb;
}

.file-drop-zone:hover {
  border-color: #0d6efd;
  background-color: #f0f7ff;
}

.file-drop-zone.has-file {
  border-style: solid;
  border-color: #0d6efd;
  background-color: #f0f7ff;
}

.file-drop-zone.is-loading {
  border-style: solid;
  border-color: #0d6efd;
  background-color: #f8f9fa;
  cursor: default;
  opacity: 0.9;
}

.file-drop-zone.is-dragging {
  border-style: dashed;
  border-color: #0d6efd;
  border-width: 3px;
  background-color: #e6f2ff;
  transform: scale(1.01);
  box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

.upload-loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 1rem;
}

.spinner-container {
  margin-bottom: 0.5rem;
}

.csv-validation-error {
  color: #dc3545;
  background-color: #f8d7da;
  border: 1px solid #f5c2c7;
  border-radius: 4px;
  padding: 0.75rem;
  font-size: 0.9rem;
}

/* CSV File Preview Styling */
.csv-file-preview {
  background-color: #f0f9ff;
  border: 1px solid #b6d4fe;
  border-radius: 8px;
  padding: 1rem;
  margin-bottom: 1rem;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
}

.preview-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.75rem;
  border-bottom: 1px solid #d9e8fd;
  padding-bottom: 0.5rem;
}

.preview-header h6 {
  margin: 0;
  color: #0a58ca;
  font-weight: 600;
  display: flex;
  align-items: center;
}

.btn-remove-preview {
  background: none;
  border: none;
  color: #6c757d;
  padding: 0.25rem 0.5rem;
  border-radius: 4px;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-remove-preview:hover {
  color: #dc3545;
  background-color: rgba(220, 53, 69, 0.1);
}

/* SMS Preview Modal Styling */
.sms-preview-container {
  padding: 0.5rem;
}

.message-preview-card {
  border: none;
  border-radius: 10px;
  overflow: hidden;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
}

.message-preview-card .card-header {
  padding: 0.75rem 1.25rem;
  background-color: #007bff !important;
}

.message-bubble {
  background-color: #f8f9fa;
  border-radius: 12px;
  box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
  overflow: hidden;
  max-width: 350px;
  margin: 0 auto;
  border: 1px solid #e9ecef;
}

.message-header {
  background-color: #343a40;
  color: white;
  padding: 10px 15px;
  font-size: 0.9rem;
  font-weight: 500;
  display: flex;
  align-items: center;
}

.message-content {
  padding: 15px;
  background-color: white;
  font-size: 0.95rem;
  min-height: 60px;
  white-space: pre-wrap;
  word-break: break-word;
}

.message-footer {
  padding: 8px 15px;
  background-color: #f8f9fa;
  border-top: 1px solid #e9ecef;
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.8rem;
  color: #6c757d;
}

.personalization-notice {
  padding: 8px 12px;
  background-color: #e8f4fd;
  border-radius: 6px;
  border-left: 3px solid #0d6efd;
}

/* Contact Statistics Styling */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1px;
  background-color: #dee2e6;
}

.stat-box {
  padding: 15px;
  background-color: white;
  text-align: center;
}

.stat-label {
  font-size: 0.85rem;
  color: #6c757d;
  margin-bottom: 5px;
}

.stat-value {
  font-size: 1.5rem;
  font-weight: 600;
  color: #343a40;
}

.stat-value.valid {
  color: #28a745;
}

.stat-value.invalid {
  color: #dc3545;
}

.stat-value.total {
  color: #0d6efd;
}

/* Schedule Card Styling */
.schedule-card {
  border-radius: 10px;
  overflow: hidden;
  border: none;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
  background-color: #fff3cd;
}

.schedule-card .card-body {
  display: flex;
  align-items: center;
  padding: 15px;
}

.schedule-icon {
  font-size: 2rem;
  color: #fd7e14;
  margin-right: 15px;
}

.schedule-info {
  flex: 1;
}

.schedule-label {
  font-size: 0.85rem;
  color: #6c757d;
  margin-bottom: 2px;
}

.schedule-date {
  font-size: 1.1rem;
  font-weight: 600;
  color: #343a40;
}

.preview-content {
  padding: 0.5rem 0;
}

.file-name {
  font-weight: 500;
  margin-bottom: 0.25rem;
  color: #212529;
  word-break: break-all;
}

.file-details {
  color: #6c757d;
  font-size: 0.875rem;
}

.file-columns {
  margin-top: 0.25rem;
}

/* Column Selector Styling */
.column-selector-container {
  background-color: #f8f9fa;
  border-radius: 6px;
  padding: 0.75rem;
  border-top: 1px solid #e9ecef;
}

.column-selector-label {
  font-weight: 500;
  margin-bottom: 0.5rem;
  color: #495057;
  font-size: 0.9rem;
}

.column-selector {
  margin-bottom: 0.5rem;
}

.column-help-text {
  font-size: 0.8rem;
  color: #6c757d;
}

.column-help-text code {
  background-color: #e9ecef;
  padding: 0.1rem 0.3rem;
  border-radius: 3px;
}

/* Message Textarea Column Selector */
.message-tools {
  position: absolute;
  right: 10px;
  top: 10px;
  z-index: 100;
}

.dropdown {
  position: relative;
}

.column-btn {
  font-size: 0.9rem;
  padding: 0.25rem 0.5rem;
  background-color: #f8f9fa;
  border-color: #dee2e6;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.column-btn:hover {
  background-color: #e9ecef;
  border-color: #ced4da;
}

.column-btn-icon {
  font-family: 'Consolas', monospace;
  font-weight: bold;
  color: #0d6efd;
}

.has-column-selector {
  padding-right: 50px; /* Make room for the column selector button */
}

/* Custom Column Dropdown */
.column-dropdown {
  position: absolute;
  top: 100%;
  right: 0;
  width: 200px;
  background-color: white;
  border: 1px solid #dee2e6;
  border-radius: 4px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
  z-index: 1000;
  margin-top: 5px;
  max-height: 250px;
}

.column-dropdown-header {
  font-weight: 600;
  color: #495057;
  padding: 0.5rem 1rem;
  border-bottom: 1px solid #e9ecef;
  background-color: #f8f9fa;
  position: sticky;
  top: 0;
}

.column-list {
  max-height: 200px;
  overflow-y: auto;
}

.column-item {
  display: block;
  width: 100%;
  text-align: left;
  padding: 0.5rem 1rem;
  border: none;
  background: none;
  cursor: pointer;
  font-size: 0.9rem;
  color: #212529;
}

.column-item:hover {
  background-color: #f8f9fa;
  color: #0d6efd;
}

.no-columns {
  padding: 0.5rem 1rem;
  color: #6c757d;
  font-style: italic;
  font-size: 0.9rem;
}

.counter-container {
  position: absolute;
  bottom: 0;
  right: 0;
  padding: 0.5rem 1rem;
}

/* Message counter styling */
.message-counter {
  font-size: 0.85rem;
  color: #6c757d;
}

.message-counter.text-warning {
  color: #ffc107 !important;
}

.message-counter.text-danger {
  color: #dc3545 !important;
}

.message-counter.over-limit {
  font-weight: 700;
}

.upload-icon {
  font-size: 2.5rem;
  color: #6b7280;
  margin-bottom: 1rem;
}

.upload-text {
  font-size: 1rem;
  font-weight: 500;
  color: #374151;
  margin-bottom: 0.5rem;
}

.upload-subtext {
  font-size: 0.875rem;
  color: #6b7280;
}

.file-preview {
  display: flex;
  align-items: center;
  text-align: left;
}

.file-icon {
  font-size: 2rem;
  color: #0d6efd;
  margin-right: 1rem;
}

.file-info {
  flex: 1;
}

.file-name {
  font-weight: 500;
  color: #111827;
  word-break: break-all;
}

.file-size {
  font-size: 0.875rem;
  color: #6b7280;
}

.btn-remove-file {
  background: none;
  border: none;
  color: #ef4444;
  cursor: pointer;
  padding: 0.5rem;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
}

.btn-remove-file:hover {
  background-color: rgba(239, 68, 68, 0.1);
}

.csv-format-info {
  background-color: #f0f7ff;
  border-radius: 8px;
  padding: 1rem;
  margin-top: 1.5rem;
}

.csv-format-info h6 {
  color: #0d6efd;
  font-weight: 600;
  margin-bottom: 0.75rem;
  display: flex;
  align-items: center;
}

.csv-format-info ul {
  margin-bottom: 0;
  padding-left: 1.5rem;
}

.csv-format-info li {
  margin-bottom: 0.25rem;
  font-size: 0.9rem;
  color: #4b5563;
}

#previewModal .card-body{ padding: 3px !important;}
</style>
