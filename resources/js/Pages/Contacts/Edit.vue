<template>
  <DashboardLayout>
    <div class="container-fluid">
      <div class="row mb-3">
        <div class="col-12">
          <h2>Edit Contact</h2>
          <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
              <li class="breadcrumb-item">
                <Link :href="route('contacts.index')">Contacts</Link>
              </li>
              <li class="breadcrumb-item active">Edit</li>
            </ol>
          </nav>
        </div>
      </div>

      <div class="card">
        <div class="card-body">
          <form @submit.prevent="submit">
            <!-- Contact Group Details -->
            <div class="mb-4">
              <h4 class="mb-3">Contact Group Details</h4>
              <div class="row">
                <div class="col-md-6">
                  <div class="mb-3">
                    <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                    <input
                      type="text"
                      id="title"
                      v-model="form.title"
                      class="form-control"
                      :class="{ 'is-invalid': form.errors.title }"
                      placeholder="Enter contact group title"
                    />
                    <div v-if="form.errors.title" class="invalid-feedback">{{ form.errors.title }}</div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="mb-3">
                    <label for="is_active" class="form-label">Status</label>
                    <select 
                      id="is_active" 
                      v-model="form.is_active" 
                      class="form-select"
                      :class="{ 'is-invalid': form.errors.is_active }"
                    >
                      <option :value="1">Active</option>
                      <option :value="0">Inactive</option>
                    </select>
                    <div v-if="form.errors.is_active" class="invalid-feedback">{{ form.errors.is_active }}</div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Contact List -->
            <div class="mb-4">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <h4>Contact List</h4>
                <button type="button" class="btn btn-sm btn-success" @click="addContact">
                  <i class="fas fa-plus me-1"></i> Add Contact
                </button>
              </div>

              <div v-if="form.contact_lists.length === 0" class="alert alert-info">
                No contacts added yet. Click the "Add Contact" button to add contacts.
              </div>

              <div v-else>
                <div class="table-responsive">
                  <table class="table table-bordered">
                    <thead>
                      <tr>
                        <th style="width: 40%">Name</th>
                        <th style="width: 40%">Telephone</th>
                        <th style="width: 20%">Action</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="(contact, index) in form.contact_lists" :key="index">
                        <td>
                          <input
                            type="text"
                            v-model="contact.name"
                            class="form-control"
                            :class="{ 'is-invalid': form.errors[`contact_lists.${index}.name`] }"
                            placeholder="Contact name"
                          />
                          <div v-if="form.errors[`contact_lists.${index}.name`]" class="invalid-feedback">
                            {{ form.errors[`contact_lists.${index}.name`] }}
                          </div>
                        </td>
                        <td>
                          <input
                            type="text"
                            v-model="contact.telephone"
                            class="form-control"
                            :class="{ 'is-invalid': form.errors[`contact_lists.${index}.telephone`] }"
                            placeholder="Phone number"
                          />
                          <div v-if="form.errors[`contact_lists.${index}.telephone`]" class="invalid-feedback">
                            {{ form.errors[`contact_lists.${index}.telephone`] }}
                          </div>
                        </td>
                        <td>
                          <button type="button" class="btn btn-sm btn-danger" @click="removeContact(index)">
                            <i class="fas fa-trash"></i>
                          </button>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>

            <div class="d-flex justify-content-between">
              <Link :href="route('contacts.index')" class="btn btn-secondary">Cancel</Link>
              <button type="submit" class="btn btn-primary" :disabled="form.processing">
                <i class="fas fa-save me-1"></i> Update Contact
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>

<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';

const props = defineProps({
  contact: Object,
  contactLists: Array
});

const form = useForm({
  title: props.contact.title,
  is_active: props.contact.is_active,
  contact_lists: props.contactLists.map(list => ({
    id: list.id,
    name: list.name,
    telephone: list.telephone
  })),
  updated_by: null
});

const addContact = () => {
  form.contact_lists.push({
    name: '',
    telephone: ''
  });
};

const removeContact = (index) => {
  form.contact_lists.splice(index, 1);
};

const submit = () => {
  form.put(route('contacts.update', props.contact.id), {
    onSuccess: () => {
      // Form submission successful
    }
  });
};
</script>
