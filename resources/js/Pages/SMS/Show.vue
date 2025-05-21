<template>
  <DashboardLayout>
    <div class="row mb-2">
      <div class="col-12">
        <div class="card">
          <div class="card-header" style="background-color: #007bff;">
            <h3 class="card-title d-flex align-items-center text-white">
              <span>{{ text.text_title }}</span>
              <span class="badge ms-2 ml-2" :class="'bg-' + (progressData?.color_code || text.status?.color_code)">
                {{ progressData?.status_name || text.status?.text_status_name || 'Unknown' }}
              </span>
            </h3>
            <div class="card-tools">
              <a :href="route('sms.index')" class="btn btn-light btn-sm">
                <i class="fas fa-arrow-left mr-1"></i> Back to List
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Progress Summary Section -->
    <div class="row mb-4">
      <div class="col-md-3 col-sm-6">
        <div class="small-box bg-light shadow-sm">
          <div class="inner">
            <h3>{{ text.contacts_count }}</h3>
            <p>Total Contacts</p>
            <div class="progress" style="height: 8px;">
              <div 
                class="progress-bar" 
                :style="{ width: (progressData?.percentage || 0) + '%', backgroundColor: progressData?.percentage === 100 ? '#28a745' : '#17a2b8' }"
              ></div>
            </div>
            <p class="mt-2 mb-0">
              <span class="text-muted">Overall Progress: </span>
              <span class="font-weight-bold" :style="{ color: progressData?.percentage === 100 ? '#28a745' : '#17a2b8' }">
                {{ progressData?.percentage || 0 }}% 
                <span v-if="progressData?.processed && progressData?.total">
                  ({{ progressData?.processed }}/{{ progressData?.total }})
                </span>
              </span>
            </p>
          </div>
        </div>
      </div>

      <!-- Delivered SMS Box -->
      <div class="col-md-3 col-sm-6">
        <div class="small-box bg-success">
          <div class="inner">
            <h3>{{ smsStats.delivered || 0 }}</h3>
            <p>SMS DELIVERED</p>
          </div>
          <div class="icon">
            <i class="fas fa-check-circle"></i>
          </div>
          <a class="small-box-footer">
            View Delivered SMS <i class="fas fa-arrow-circle-right"></i>
          </a>
        </div>
      </div>

      <!-- In Queue SMS Box -->
      <div class="col-md-3 col-sm-6">
        <div class="small-box bg-warning">
          <div class="inner">
            <h3>{{ smsStats.queued || 0 }}</h3>
            <p>SMS IN QUEUE</p>
          </div>
          <div class="icon">
            <i class="fas fa-hourglass-half"></i>
          </div>
          <a class="small-box-footer">
            View SMS in Queue <i class="fas fa-arrow-circle-right"></i>
          </a>
        </div>
      </div>

      <!-- Failed SMS Box -->
      <div class="col-md-3 col-sm-6">
        <div class="small-box bg-danger">
          <div class="inner">
            <h3>{{ smsStats.failed || 0 }}</h3>
            <p>SMS UNDELIVERED</p>
          </div>
          <div class="icon">
            <i class="fas fa-times-circle"></i>
          </div>
          <a class="small-box-footer">
            View Undelivered SMS <i class="fas fa-arrow-circle-right"></i>
          </a>
        </div>
      </div>
    </div>

    <!-- Message Content & Details Cards -->
    <div class="row">
      <!-- Message Content Card -->
      <div class="col-md-6">
        <div class="card shadow-sm border-0">
          <div class="card-header bg-light">
            <h5 class="mb-0"><i class="fas fa-comment mr-2"></i>Message Content</h5>
          </div>
          <div class="card-body">
            <div class="border p-3 rounded bg-light shadow-sm">
              <p class="mb-0" style="white-space: pre-wrap;">{{ text.message }}</p>
            </div>
            <div class="d-flex justify-content-between mt-2 text-muted">
              <small>{{ text.message.length }} / 300 characters</small>
              <small v-if="text.scheduled" class="badge bg-info p-1">
                Scheduled: {{ formatDate(text.schedule_date) }}
              </small>
              <small v-else class="badge bg-success p-1">Immediate send</small>
            </div>
          </div>
        </div>
      </div>

      <!-- Export Report Section -->
      <div class="col-md-6">
        <div class="card shadow-sm border-0">
          <div class="card-header bg-light">
            <h5 class="mb-0"><i class="fas fa-file-export mr-2"></i>Export Report</h5>
          </div>
          <div class="card-body">
            <p class="text-muted">Export SMS delivery data to Excel format</p>
            
            <div class="d-flex justify-content-end">
              <a :href="route('sms.export.detail', props.text.id)" class="btn btn-primary" target="_blank">
                <i class="fas fa-file-excel mr-1"></i> Export to Excel
              </a>
            </div>
          </div>
        </div>
      </div>
      
      <!-- SMS Details Card -->
      <div class="col-md-12 mt-4">
        <div class="card shadow-sm border-0">
          <div class="card-header bg-light">
            <h5 class="mb-0"><i class="fas fa-info-circle mr-2"></i>SMS Information</h5>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="table table-hover">
                <tbody>
                  <tr>
                    <th style="width: 200px;">Created By</th>
                    <td>{{ text.creator?.name || 'System' }}</td>
                  </tr>
                  <tr>
                    <th>Created At</th>
                    <td>{{ formatDate(text.created_at) }}</td>
                  </tr>
                  <tr>
                    <th>Last Updated</th>
                    <td>{{ formatDate(text.updated_at) }}</td>
                  </tr>
                  <tr>
                    <th>Contact Source</th>
                    <td>
                      <span class="badge bg-info">
                        {{ text.contact_type === 'manual' ? 'Manual Entry' : 
                          text.contact_type === 'csv' ? 'CSV Import' : 
                          text.contact_type === 'list' ? 'Contact Lists' : 'Unknown' }}
                      </span>
                    </td>
                  </tr>
                  <tr v-if="text.contact_type === 'csv'">
                    <th>CSV File</th>
                    <td>
                      <div class="d-flex align-items-center">
                        <span>{{ text.csv_file_name }}</span>
                        <a 
                          v-if="text.csv_file_name" 
                          :href="route('sms.download.csv', text.csv_file_name)" 
                          class="btn btn-sm btn-success ml-3"
                          target="_blank"
                          download
                        >
                          <i class="fas fa-download mr-1"></i> Download CSV
                        </a>
                      </div>
                      <small class="text-muted" v-if="text.csv_file_columns">
                        <strong>Columns:</strong> {{ formatColumns(text.csv_file_columns) }}
                      </small>
                    </td>
                  </tr>
                  <tr v-if="text.contact_type === 'list'">
                    <th>Contact Lists</th>
                    <td>
                      <div v-if="contactLists.length > 0">
                        <div v-for="(list, index) in contactLists" :key="index" class="mb-2">
                          <a :href="route('contacts.show', list.id)" class="btn btn-sm btn-info mr-2">
                            <i class="fas fa-eye mr-1"></i> View
                          </a>
                          <span>{{ list.name }}</span>
                          <span class="badge badge-primary ml-2">{{ list.contacts_count || 0 }} contacts</span>
                        </div>
                      </div>
                      <div v-else-if="loadingContactLists" class="text-center py-2">
                        <div class="spinner-border spinner-border-sm text-primary mr-2" role="status">
                          <span class="sr-only">Loading...</span>
                        </div>
                        <span>Loading contact lists...</span>
                      </div>
                      <div v-else class="text-muted">No contact lists found</div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Recipients List (only if manual contacts) -->
    <div class="row mt-4" v-if="text.contact_type === 'manual' && text.recepient_contacts">
      <div class="col-12">
        <div class="card shadow-sm border-0">
          <div class="card-header bg-light">
            <h5 class="mb-0"><i class="fas fa-users mr-2"></i>Recipients</h5>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="table table-sm table-hover table-striped">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Phone Number</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(contact, index) in parseContacts(text.recepient_contacts)" :key="index">
                    <td>{{ index + 1 }}</td>
                    <td>{{ contact }}</td>
                    <td>
                      <span class="badge" :class="getContactStatusBadge(contact)">{{ getContactStatus(contact) }}</span>
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
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { ref, reactive, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

// Props from controller
const props = defineProps({
  text: Object
});

// State for progress tracking and SMS stats
const progressData = ref(null);
const progressInterval = ref(null);

// State for contact lists
const contactLists = ref([]);
const loadingContactLists = ref(false);

// Function to fetch contact lists if the SMS uses contact lists
const fetchContactLists = async () => {
  if (props.text.contact_type !== 'list' || !props.text.contact_list) {
    return;
  }
  
  loadingContactLists.value = true;
  
  try {
    // Parse the contact list IDs
    let contactListIds = [];
    try {
      // Try parsing as JSON first - this is the format we expect from the database
      // as seen in the SQL example: '["2","3","4"]'
      contactListIds = JSON.parse(props.text.contact_list);
      
      // If parsed successfully but not an array, convert to array
      if (!Array.isArray(contactListIds)) {
        contactListIds = [contactListIds.toString()];
      }
    } catch (e) {
      console.warn('Error parsing contact list JSON:', e);
      // If not valid JSON, split by comma
      contactListIds = props.text.contact_list.split(',').map(id => id.trim());
    }
    
    if (contactListIds.length === 0) {
      loadingContactLists.value = false;
      return;
    }
    
    console.log('Fetching contact lists for IDs:', contactListIds);
    
    // Fetch contact list details from the server
    const response = await axios.get(route('api.contacts.lists', {
      ids: contactListIds.join(',')
    }));
    
    if (response.data && response.data.lists) {
      contactLists.value = response.data.lists;
      console.log('Contact lists loaded:', contactLists.value);
    }
  } catch (error) {
    console.error('Error fetching contact lists:', error);
  } finally {
    loadingContactLists.value = false;
  }
};
const contactStatuses = ref({});
const smsStats = reactive({
  delivered: 0,
  queued: 0,
  failed: 0,
  blacklisted: 0
});

// Export filters
const exportFilters = reactive({
  delivered: false,
  failed: false,
  queued: false
});

// Function to fetch progress data
const fetchProgressData = async () => {
  try {
    console.log('Fetching progress data for SMS ID:', props.text.id);
    const response = await axios.get(route('sms.progress'), {
      params: { text_ids: [props.text.id] }
    });
    
    // Update progress data
    if (response.data && response.data[props.text.id]) {
      console.log('Progress data received:', response.data[props.text.id]);
      progressData.value = response.data[props.text.id];
    }
    
    // Update SMS stats
    await fetchSmsStats();
  } catch (error) {
    console.error('Error fetching progress data:', error);
  }
};

// Function to fetch real SMS stats from the server
const fetchSmsStats = async () => {
  try {
    console.log('Fetching SMS statistics for SMS ID:', props.text.id);
    const response = await axios.get(route('sms.statistics'), {
      params: { 
        text_id: props.text.id,
        include_contacts: true // Get individual contact statuses
      }
    });
    
    // Update stats with real data from the server
    console.log('SMS statistics received:', response.data);
    
    smsStats.delivered = response.data.delivered || 0;
    smsStats.queued = response.data.queued || 0;
    smsStats.failed = response.data.failed || 0;
    smsStats.blacklisted = response.data.blacklisted || 0;
    
    // Store contact statuses for individual contact status display
    if (response.data.contacts) {
      contactStatuses.value = response.data.contacts;
    }
  } catch (error) {
    console.error('Error fetching SMS statistics:', error);
  }
};

// Function to determine contact status based on server data
const getContactStatus = (contact) => {
  // First check if we have status data for this contact
  if (contactStatuses.value && contactStatuses.value[contact]) {
    // Return the actual status from the server
    return contactStatuses.value[contact].status || 'Queued';
  }
  
  // If we have no status data for this contact yet, it's likely still in queue
  if (progressData.value && progressData.value.percentage === 100) {
    // If progress is 100%, assume all unlisted contacts are delivered
    return 'Delivered';
  } else {
    // Otherwise, they're pending/queued
    return 'Queued';
  }
};

// Function to get badge class based on contact status
const getContactStatusBadge = (contact) => {
  const status = getContactStatus(contact).toLowerCase();
  switch (status) {
    case 'delivered': return 'bg-success';
    case 'queued': 
    case 'pending': return 'bg-warning';
    case 'failed': return 'bg-danger';
    case 'blacklisted': return 'bg-dark';
    default: return 'bg-secondary';
  }
};

// Setup polling when component mounts (at 30-second intervals to reduce system load)
onMounted(() => {
  console.log('SMS details component mounted, setting up progress polling');
  
  // Fetch initial data
  fetchProgressData();
  
  // Fetch contact lists if this SMS uses contact lists
  if (props.text.contact_type === 'list') {
    fetchContactLists();
  }
  
  // Setup interval for updates every 30 seconds to reduce system load
  progressInterval.value = setInterval(() => {
    console.log('Polling for progress update (30-second interval)');
    fetchProgressData();
  }, 30000); // 30 seconds
});

// Cleanup interval when component unmounts
onUnmounted(() => {
  console.log('SMS details component unmounting, cleaning up');
  if (progressInterval.value) {
    clearInterval(progressInterval.value);
    progressInterval.value = null;
  }
});

// Helper functions
const formatDate = (dateString) => {
  if (!dateString) return '';
  const date = new Date(dateString);
  return date.toLocaleString();
};

const parseContacts = (contactsString) => {
  if (!contactsString) return [];
  return contactsString.split(',').filter(contact => !!contact.trim());
};

const formatColumns = (columnsJson) => {
  if (!columnsJson) return '';
  try {
    const columns = JSON.parse(columnsJson);
    return columns.join(', ');
  } catch (e) {
    return columnsJson;
  }
};

const parseContactLists = (listsJson) => {
  if (!listsJson) return '';
  try {
    const lists = JSON.parse(listsJson);
    return Array.isArray(lists) ? lists.join(', ') : listsJson;
  } catch (e) {
    return listsJson;
  }
};

// Generate URL for CSV export based on selected filters
const generateExportUrl = () => {
  // Build the export URL with selected filters
  let url = route('sms.export.detail', props.text.id);
  
  // Add filter parameters
  const params = [];
  if (exportFilters.delivered) params.push('status[]=delivered');
  if (exportFilters.failed) params.push('status[]=failed');
  if (exportFilters.queued) params.push('status[]=queued');
  
  // If no filters selected, include all
  if (params.length === 0) {
    params.push('status[]=delivered');
    params.push('status[]=failed');
    params.push('status[]=queued');
  }
  
  // Append parameters to URL
  if (params.length > 0) {
    url += '?' + params.join('&');
  }
  
  return url;
};
</script>

<style scoped>
.small-box {
  border-radius: 0.25rem;
  box-shadow: 0 0 1px rgba(0,0,0,.125), 0 1px 3px rgba(0,0,0,.2);
  display: block;
  margin-bottom: 20px;
  position: relative;
}

.small-box .inner {
  padding: 10px;
}

.small-box .icon {
  color: rgba(0,0,0,.15);
  font-size: 70px;
  position: absolute;
  right: 15px;
  top: 15px;
  z-index: 0;
}

.small-box h3 {
  font-size: 2.2rem;
  font-weight: 700;
  margin: 0 0 10px;
  padding: 0;
  white-space: nowrap;
}

.small-box .small-box-footer {
  background-color: rgba(0,0,0,.1);
  color: rgba(255,255,255,.8);
  display: block;
  padding: 3px 0;
  position: relative;
  text-align: center;
  text-decoration: none;
  z-index: 10;
}

.small-box .small-box-footer:hover {
  background-color: rgba(0,0,0,.15);
  color: #fff;
}

.bg-light {
  background-color: #f8f9fa!important;
}

.shadow-sm {
  box-shadow: 0 .125rem .25rem rgba(0,0,0,.075)!important;
}

.border-0 {
  border: 0!important;
}
</style>
