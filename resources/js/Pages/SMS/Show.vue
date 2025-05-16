<template>
  <DashboardLayout>
    <div class="row">
      <div class="col-12">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">SMS Details</h3>
            <div class="card-tools">
              <a :href="route('sms.index')" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left mr-1"></i> Back to List
              </a>
            </div>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-6">
                <div class="card">
                  <div class="card-header bg-light">
                    <h5 class="mb-0">General Information</h5>
                  </div>
                  <div class="card-body">
                    <dl class="row">
                      <dt class="col-sm-4">Title:</dt>
                      <dd class="col-sm-8">{{ text.text_title }}</dd>
                      
                      <dt class="col-sm-4">Status:</dt>
                      <dd class="col-sm-8">
                        <span class="badge" :style="{ backgroundColor: text.status?.color_code || '#777' }">
                          {{ text.status?.text_status_name || 'Unknown' }}
                        </span>
                      </dd>
                      
                      <dt class="col-sm-4">Recipients:</dt>
                      <dd class="col-sm-8">{{ text.contacts_count }}</dd>
                      
                      <dt class="col-sm-4">Scheduled:</dt>
                      <dd class="col-sm-8">
                        <span v-if="text.scheduled" class="badge bg-info">
                          Yes - {{ formatDate(text.schedule_date) }}
                        </span>
                        <span v-else class="badge bg-success">No - Immediate send</span>
                      </dd>
                      
                      <dt class="col-sm-4">Created:</dt>
                      <dd class="col-sm-8">{{ formatDate(text.created_at) }}</dd>
                    </dl>
                  </div>
                </div>
              </div>
              
              <div class="col-md-6">
                <div class="card">
                  <div class="card-header bg-light">
                    <h5 class="mb-0">Message Content</h5>
                  </div>
                  <div class="card-body">
                    <div class="border p-3 rounded bg-light">
                      {{ text.message }}
                    </div>
                    <div class="mt-2 text-muted">
                      <small>{{ text.message.length }} / 300 characters</small>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
            <div class="row mt-4">
              <div class="col-12">
                <div class="card">
                  <div class="card-header bg-light">
                    <h5 class="mb-0">Recipients Information</h5>
                  </div>
                  <div class="card-body">
                    <div v-if="text.contact_type === 'manual'">
                      <h6>Manually Entered Contacts</h6>
                      <div class="table-responsive" v-if="text.recepient_contacts">
                        <table class="table table-sm table-bordered">
                          <thead>
                            <tr>
                              <th>#</th>
                              <th>Phone Number</th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr v-for="(contact, index) in parseContacts(text.recepient_contacts)" :key="index">
                              <td>{{ index + 1 }}</td>
                              <td>{{ contact }}</td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </div>
                    
                    <div v-else-if="text.contact_type === 'csv'">
                      <h6>CSV File Import</h6>
                      <p>File: {{ text.csv_file_name }}</p>
                      <p v-if="text.csv_file_columns">
                        Columns: {{ formatColumns(text.csv_file_columns) }}
                      </p>
                    </div>
                    
                    <div v-else-if="text.contact_type === 'list'">
                      <h6>Contact Lists</h6>
                      <p>Selected lists: {{ parseContactLists(text.contact_list) }}</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>

<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue';

// Props from controller
const props = defineProps({
  text: Object
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
</script>
