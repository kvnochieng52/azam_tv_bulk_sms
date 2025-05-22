<template>
  <DashboardLayout>
    <div class="row mb-3">
      <div class="col-12">
        <div class="card">
          <div class="card-header">
            <h4>Edit User</h4>
          </div>
          <div class="card-body">
            <form @submit.prevent="submit">
              <div class="mb-3">
                <label for="full_names" class="form-label">Full Names</label>
                <input
                  type="text"
                  class="form-control"
                  :class="{ 'is-invalid': form.errors.full_names }"
                  id="full_names"
                  v-model="form.full_names"
                  required
                />
                <div v-if="form.errors.full_names" class="invalid-feedback">
                  {{ form.errors.full_names }}
                </div>
              </div>

              <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input
                  type="email"
                  class="form-control"
                  :class="{ 'is-invalid': form.errors.email }"
                  id="email"
                  v-model="form.email"
                  required
                />
                <div v-if="form.errors.email" class="invalid-feedback">
                  {{ form.errors.email }}
                </div>
              </div>

              <div class="mb-3">
                <label for="telephone" class="form-label">Telephone</label>
                <input
                  type="text"
                  class="form-control"
                  :class="{ 'is-invalid': form.errors.telephone }"
                  id="telephone"
                  v-model="form.telephone"
                  required
                />
                <div v-if="form.errors.telephone" class="invalid-feedback">
                  {{ form.errors.telephone }}
                </div>
              </div>

              <div class="mb-3">
                <label for="password" class="form-label"
                  >Password (leave blank to keep current)</label
                >
                <input
                  type="password"
                  class="form-control"
                  :class="{ 'is-invalid': form.errors.password }"
                  id="password"
                  v-model="form.password"
                />
                <div v-if="form.errors.password" class="invalid-feedback">
                  {{ form.errors.password }}
                </div>
              </div>

              <div class="mb-3">
                <label for="password_confirmation" class="form-label"
                  >Confirm Password</label
                >
                <input
                  type="password"
                  class="form-control"
                  id="password_confirmation"
                  v-model="form.password_confirmation"
                />
              </div>

              <div class="mb-3">
                <label for="role" class="form-label">Role</label>
                <select
                  class="form-select form-control"
                  :class="{ 'is-invalid': form.errors.role }"
                  id="role"
                  v-model="form.role"
                  required
                >
                  <option v-for="role in roles" :key="role.id" :value="role.id">
                    {{ role.name }}
                  </option>
                </select>
                <div v-if="form.errors.role" class="invalid-feedback">
                  {{ form.errors.role }}
                </div>
              </div>

              <div class="mb-3 form-check">
                <input
                  type="checkbox"
                  class="form-check-input mr-2"
                  :class="{ 'is-invalid': form.errors.active }"
                  id="active"
                  v-model="form.active"
                  true-value="1"
                  false-value="0"
                />
                <label class="form-check-label" for="active">Active</label>
                <div v-if="form.errors.active" class="invalid-feedback">
                  {{ form.errors.active }}
                </div>
              </div>

              <div v-if="form.hasErrors" class="alert alert-danger">
                <ul class="mb-0">
                  <li v-for="error in form.errors" :key="error">
                    {{ error }}
                  </li>
                </ul>
              </div>

              <button
                type="submit"
                class="btn btn-primary"
                :disabled="form.processing"
              >
                <span v-if="form.processing">
                  <span
                    class="spinner-border spinner-border-sm me-2"
                    role="status"
                    aria-hidden="true"
                  ></span>
                  Updating...
                </span>
                <span v-else>Update</span>
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>
  
  <script setup>
import DashboardLayout from "@/Layouts/DashboardLayout.vue";
import { useForm } from "@inertiajs/vue3";
import Toast from "@/Services/toast";

const props = defineProps({
  user: Object,
  roles: Array,
});

const form = useForm({
  full_names: props.user.name,
  email: props.user.email,
  telephone: props.user.telephone,
  password: "",
  password_confirmation: "",
  role: props.user.roles[0]?.id,
  active: props.user.active ?? props.user.is_active,
});

const submit = () => {
  form.put(`/users/${props.user.id}`, {
    preserveScroll: true,
    onSuccess: () => {
      Toast.success("User updated successfully!");
    },
    onError: (errors) => {
      Toast.error("Please fix the errors in the form.");
    },
  });
};
</script>
  
  <style scoped>
.card-header {
  background: linear-gradient(135deg, #007bff, #005bb5);
  border-bottom: none;
  padding: 1rem 1.5rem;
  color: white;
  border-radius: 0.5rem 0.5rem 0 0 !important;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.card-header h4 {
  margin: 0;
  text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
  font-size: 18px;
}

.invalid-feedback {
  display: block;
  margin-top: 0.25rem;
  font-size: 0.875em;
  color: #dc3545;
}

.is-invalid {
  border-color: #dc3545;
}
</style>