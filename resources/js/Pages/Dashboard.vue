<script setup>
import { ref } from "vue";
import { Head, Link } from "@inertiajs/vue3";
import DashboardLayout from "../Layouts/DashboardLayout.vue";
import StatCard from "../Components/Dashboard/StatCard.vue";
import ChartBox from "../Components/Dashboard/ChartBox.vue";
import TableCard from "../Components/Dashboard/TableCard.vue";

// Get stats from props with fallback defaults
const props = defineProps({
  stats: {
    type: Object,
    default() {
      return {
        smsSent: 1250,
        recipients: 150,
        failedDeliveries: 12,
        smsCredit: 51,
      };
    },
  },
});

// Format numbers with commas
function formatNumber(num) {
  return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

// Chart data with simplified, attractive colors
const deliveryChartData = {
  type: "pie",
  data: {
    labels: ["Delivered", "Failed", "Pending"],
    datasets: [
      {
        data: [85, 5, 10],
        backgroundColor: ["#2563eb", "#ef4444", "#f59e0b"],
        borderWidth: 0,
      },
    ],
  },
};

const monthlyChartData = {
  type: "bar",
  data: {
    labels: ["January", "February", "March", "April", "May", "June"],
    datasets: [
      {
        label: "SMS Sent",
        data: [356, 289, 475, 310, 419, 245],
        backgroundColor: "#2563eb",
        borderRadius: 6,
        maxBarThickness: 40,
      },
    ],
  },
};

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: "top",
    },
    tooltip: {
      backgroundColor: "#1f2937",
      padding: 12,
      cornerRadius: 8,
    },
  },
};

// Table data
const smsColumns = [
  { field: "recipient", label: "Recipient" },
  { field: "message", label: "Message" },
  { field: "status", label: "Status" },
  { field: "sentAt", label: "Sent At" },
];

const recentSms = ref([
  {
    id: 1,
    recipient: "+255 712 345 678",
    message: "Your subscription has been renewed",
    status: "Delivered",
    sentAt: "2023-05-01 14:30",
  },
  {
    id: 2,
    recipient: "+255 765 432 109",
    message: "Payment confirmation",
    status: "Failed",
    sentAt: "2023-05-02 09:15",
  },
  {
    id: 3,
    recipient: "+255 733 221 444",
    message: "New content available",
    status: "Delivered",
    sentAt: "2023-05-02 16:45",
  },
  {
    id: 4,
    recipient: "+255 788 123 456",
    message: "Account verification code",
    status: "Pending",
    sentAt: "2023-05-03 10:20",
  },
  {
    id: 5,
    recipient: "+255 744 555 666",
    message: "Special offer notification",
    status: "Delivered",
    sentAt: "2023-05-03 13:10",
  },
]);

// Event handlers
function handleStatClick(type) {
  console.log(`Stat clicked: ${type}`);
  window.location.href = "/sms";
}

function viewSms(sms) {
  console.log("View SMS:", sms);
}

function editSms(sms) {
  console.log("Edit SMS:", sms);
}

function deleteSms(sms) {
  console.log("Delete SMS:", sms);
}
</script>

<template>
  <Head title="Dashboard" />

  <DashboardLayout>
    <!-- Welcome Banner -->
    <div class="row mb-4">
      <div class="col-12">
        <div class="card bg-primary">
          <div class="card-body d-flex align-items-center p-4">
            <div>
              <h2 class="mb-1 text-white">
                Welcome to Azam TV Bulk SMS Dashboard
              </h2>
              <p class="mb-0 text-white opacity-75">
                Monitor your SMS campaigns and analytics in one place
              </p>
            </div>
            <div class="ml-auto">
              <Link
                :href="route('sms.create')"
                class="btn btn-light"
                :class="{ active: $page.url.startsWith('/sms/send') }"
              >
                <i class="fas fa-paper-plane mr-2"></i> Send New Campaign
              </Link>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Stats Row with custom colors -->
    <div class="row mb-4">
      <StatCard
        title="Total SMS Sent"
        :value="formatNumber(stats.smsSent)"
        icon="fas fa-envelope"
        bg-color="bg-info"
        link-text="View All Messages"
        @click="handleStatClick('sms-sent')"
      />
      <StatCard
        title="Recipients"
        :value="formatNumber(stats.recipients)"
        icon="fas fa-users"
        bg-color="bg-primary"
        link-text="Manage Recipients"
        @click="handleStatClick('recipients')"
      />
      <StatCard
        title="Failed Deliveries"
        :value="formatNumber(stats.failedDeliveries)"
        icon="fas fa-exclamation-circle"
        bg-color="bg-warning"
        link-text="View Failed Messages"
        @click="handleStatClick('failed')"
      />
      <StatCard
        title="SMS Credit"
        :value="formatNumber(stats.smsCredit)"
        icon="fas fa-credit-card"
        bg-color="bg-success"
        link-text="View Sms Credit"
        @click="handleStatClick('credit')"
      />
    </div>

    <!-- Charts Row with better spacing -->
    <div class="row mb-4">
      <div class="col-md-6">
        <ChartBox
          title="SMS Delivery Statistics"
          chart-id="deliveryChart"
          :chart-data="deliveryChartData"
          :chart-options="chartOptions"
        />
      </div>
      <div class="col-md-6">
        <ChartBox
          title="SMS Sent Per Month"
          chart-id="monthlyChart"
          :chart-data="monthlyChartData"
          :chart-options="chartOptions"
        />
      </div>
    </div>

    <!-- Quick Actions Section -->
    <div class="row mb-4">
      <div class="col-12">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Quick Actions</h3>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-3 col-sm-6">
                <a
                  href="#"
                  class="btn btn-lg btn-block btn-outline-primary mb-3"
                >
                  <i
                    class="fas fa-paper-plane mb-2 d-block"
                    style="font-size: 24px"
                  ></i>
                  Send SMS
                </a>
              </div>
              <div class="col-md-3 col-sm-6">
                <a href="#" class="btn btn-lg btn-block btn-outline-info mb-3">
                  <i
                    class="fas fa-users mb-2 d-block"
                    style="font-size: 24px"
                  ></i>
                  Add Recipients
                </a>
              </div>
              <div class="col-md-3 col-sm-6">
                <a
                  href="#"
                  class="btn btn-lg btn-block btn-outline-success mb-3"
                >
                  <i
                    class="fas fa-file-alt mb-2 d-block"
                    style="font-size: 24px"
                  ></i>
                  Templates
                </a>
              </div>
              <div class="col-md-3 col-sm-6">
                <a
                  href="#"
                  class="btn btn-lg btn-block btn-outline-secondary mb-3"
                >
                  <i
                    class="fas fa-chart-line mb-2 d-block"
                    style="font-size: 24px"
                  ></i>
                  Reports
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Recent SMS Table with improved styling -->
    <div class="row">
      <div class="col-md-12">
        <TableCard
          title="Recent SMS Messages"
          :columns="smsColumns"
          :items="[]"
          show-search
          has-pagination
          @view="viewSms"
          @edit="editSms"
          @delete="deleteSms"
        />
      </div>
    </div>
  </DashboardLayout>
</template>
