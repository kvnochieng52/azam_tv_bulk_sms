<script setup>
import { ref, onMounted, onUnmounted, computed } from "vue";
import { Head, Link, router } from "@inertiajs/vue3";
import axios from "axios";
import DashboardLayout from "../Layouts/DashboardLayout.vue";
import StatCard from "../Components/Dashboard/StatCard.vue";
import ChartBox from "../Components/Dashboard/ChartBox.vue";
import TableCard from "../Components/Dashboard/TableCard.vue";

// Reactive data
const balances = ref([]);      // per-country balances
const balanceError = ref(null);
const isBalanceLoading = ref(false);
const lastUpdated = ref(null);

// Stats with reactive data
const stats = ref({
  smsSent: 0,
  inQueue: 0,
  failedDeliveries: 0,
  smsCredit: 0, // unused, kept for layout compatibility
});
const isStatsLoading = ref(false);
const statsError = ref(null);

// Monthly stats for bar chart
const monthlyStats = ref({
  months: [],
  counts: [],
});
const isMonthlyStatsLoading = ref(false);
const monthlyStatsError = ref(null);

// Recent SMS messages
const recentSms = ref([]);
const isRecentSmsLoading = ref(false);
const recentSmsError = ref(null);

// Computed delivery chart data based on stats
const deliveryChartData = computed(() => ({
  type: "pie",
  data: {
    labels: ["Delivered", "Failed", "Pending"],
    datasets: [
      {
        data: [
          stats.value.smsSent, // Delivered
          stats.value.failedDeliveries, // Failed
          stats.value.inQueue, // Pending
        ],
        backgroundColor: ["#2563eb", "#ef4444", "#f59e0b"],
        borderWidth: 0,
      },
    ],
  },
}));

// Computed monthly chart data
const monthlyChartData = computed(() => ({
  type: "bar",
  data: {
    labels: monthlyStats.value.months,
    datasets: [
      {
        label: "SMS Sent",
        data: monthlyStats.value.counts,
        backgroundColor: "#2563eb",
        borderRadius: 6,
        maxBarThickness: 40,
      },
    ],
  },
}));

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

// Format balance string e.g. "KES 120.50" → "KES 120.50"
const formatBalance = (bal) => {
  if (!bal) return "0.00";
  const parts = bal.split(" ");
  const amount = parseFloat(parts[1] ?? bal).toFixed(2);
  return new Intl.NumberFormat("en-US", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount);
};

const formatBalanceFull = (bal) => {
  if (!bal) return "N/A";
  const parts = bal.split(" ");
  return `${parts[0]} ${formatBalance(bal)}`;
};

// Fetch per-country balances from API
const fetchBalance = async () => {
  try {
    isBalanceLoading.value = true;
    balanceError.value = null;
    const response = await axios.get(route("dashboard.balance"));

    if (response.data.success) {
      balances.value = response.data.balances;
      lastUpdated.value = new Date().toLocaleTimeString();
    } else {
      balanceError.value = response.data.message || "Failed to fetch balance";
    }
  } catch (error) {
    balanceError.value =
      error.response?.data?.message ||
      error.message ||
      "Failed to fetch balance";
    console.error("Balance fetch error:", error);
  } finally {
    isBalanceLoading.value = false;
  }
};

// Fetch stats from API (will also update pie chart)
const fetchStats = async () => {
  try {
    isStatsLoading.value = true;
    statsError.value = null;
    const response = await axios.get(route("dashboard.stats"));

    if (response.data.success) {
      stats.value.smsSent = response.data.totalSent;
      stats.value.inQueue = response.data.totalInQueue;
      stats.value.failedDeliveries = response.data.totalFailed;
    } else {
      statsError.value = response.data.message || "Failed to fetch stats";
    }
  } catch (error) {
    statsError.value =
      error.response?.data?.message || error.message || "Failed to fetch stats";
    console.error("Stats fetch error:", error);
  } finally {
    isStatsLoading.value = false;
  }
};

// Fetch monthly stats for bar chart
const fetchMonthlyStats = async () => {
  try {
    isMonthlyStatsLoading.value = true;
    monthlyStatsError.value = null;
    const response = await axios.get(route("dashboard.monthly-stats"));

    if (response.data.success) {
      monthlyStats.value = {
        months: response.data.months,
        counts: response.data.counts,
      };
    } else {
      monthlyStatsError.value =
        response.data.message || "Failed to fetch monthly stats";
    }
  } catch (error) {
    monthlyStatsError.value =
      error.response?.data?.message ||
      error.message ||
      "Failed to fetch monthly stats";
    console.error("Monthly stats fetch error:", error);
  } finally {
    isMonthlyStatsLoading.value = false;
  }
};

// Fetch recent SMS messages
const fetchRecentSms = async () => {
  try {
    isRecentSmsLoading.value = true;
    recentSmsError.value = null;
    const response = await axios.get(route("dashboard.recent-sms"));

    if (response.data.success) {
      recentSms.value = response.data.messages;
    } else {
      recentSmsError.value =
        response.data.message || "Failed to fetch recent messages";
    }
  } catch (error) {
    recentSmsError.value =
      error.response?.data?.message ||
      error.message ||
      "Failed to fetch recent messages";
    console.error("Recent SMS fetch error:", error);
  } finally {
    isRecentSmsLoading.value = false;
  }
};

// Format numbers with commas
const formatNumber = (num) => {
  return num.toLocaleString("en-US", {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  });
};

// Format date for display
const formatDate = (dateString) => {
  const options = {
    year: "numeric",
    month: "short",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  };
  return new Date(dateString).toLocaleDateString(undefined, options);
};

// Table columns for recent SMS
const smsColumns = [
  { field: "title", label: "Title" },
  { field: "creator.name", label: "Created By" },
  { field: "created_at", label: "Created At" },
  { field: "status.text_status_name", label: "Status" },
  { field: "actions", label: "Actions" },
];

// Event handlers
// const handleStatClick = (type) => {
//   console.log(`Stat clicked: ${type}`);
// };

const handleStatClick = (type) => {
  console.log(`Stat clicked: ${type}`);
  router.visit("/sms"); // Using router instead of Inertia
};
const refreshAll = async () => {
  await Promise.all([
    fetchBalance(),
    fetchStats(),
    fetchMonthlyStats(),
    fetchRecentSms(),
  ]);
};

// Lifecycle hooks
onMounted(async () => {
  await refreshAll();

  // Auto-refresh every 5 minutes
  const interval = setInterval(refreshAll, 300000);
  onUnmounted(() => clearInterval(interval));
});
</script>

<template>
  <Head title="Dashboard" />

  <DashboardLayout>
    <!-- Error messages -->
    <div v-if="balanceError" class="alert alert-danger mb-4">
      <i class="fas fa-exclamation-triangle mr-2"></i> {{ balanceError }}
      <button @click="refreshAll" class="btn btn-sm btn-outline-danger ml-3">
        <i class="fas fa-sync-alt mr-1"></i> Retry
      </button>
    </div>

    <div v-if="statsError" class="alert alert-warning mb-4">
      <i class="fas fa-exclamation-triangle mr-2"></i> {{ statsError }}
      <button @click="refreshAll" class="btn btn-sm btn-outline-warning ml-3">
        <i class="fas fa-sync-alt mr-1"></i> Retry
      </button>
    </div>

    <div v-if="monthlyStatsError" class="alert alert-info mb-4">
      <i class="fas fa-exclamation-triangle mr-2"></i> {{ monthlyStatsError }}
      <button @click="refreshAll" class="btn btn-sm btn-outline-info ml-3">
        <i class="fas fa-sync-alt mr-1"></i> Retry
      </button>
    </div>

    <div v-if="recentSmsError" class="alert alert-secondary mb-4">
      <i class="fas fa-exclamation-triangle mr-2"></i> {{ recentSmsError }}
      <button @click="refreshAll" class="btn btn-sm btn-outline-secondary ml-3">
        <i class="fas fa-sync-alt mr-1"></i> Retry
      </button>
    </div>

    <!-- Welcome Banner -->
    <div class="row mb-4">
      <div class="col-12">
        <div class="card bg-primary">
          <div class="card-body d-flex align-items-center p-4">
            <div>
              <h2 class="mb-1 text-white">Welcome to SMS Dashboard</h2>
              <p class="mb-0 text-white opacity-75">
                Monitor your SMS campaigns and account balance
              </p>
            </div>
            <div class="ml-auto">
              <Link :href="route('sms.create')" class="btn btn-light">
                <i class="fas fa-paper-plane mr-2"></i> Send New SMS
              </Link>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Stats Row -->
    <div class="row mb-4">
      <StatCard
        title="Total SMS Sent"
        :value="isStatsLoading ? '...' : formatNumber(stats.smsSent)"
        icon="fas fa-envelope"
        bg-color="bg-info"
        link-text="View All Messages"
        @click="handleStatClick('sms-sent')"
      />
      <StatCard
        title="Total SMS In Queue"
        :value="isStatsLoading ? '...' : formatNumber(stats.inQueue)"
        icon="fas fa-inbox"
        bg-color="bg-primary"
        link-text="View Queue"
        @click="handleStatClick('queue')"
      />
      <StatCard
        title="Failed Deliveries"
        :value="isStatsLoading ? '...' : formatNumber(stats.failedDeliveries)"
        icon="fas fa-exclamation-circle"
        bg-color="bg-warning"
        link-text="View Failed"
        @click="handleStatClick('failed')"
      />
      <!-- Single balance card for all assigned countries -->
      <div class="col-lg-3 col-6">
        <div class="small-box balance-box">
          <div class="inner">
            <div v-if="isBalanceLoading" class="balance-loading">
              <i class="fas fa-spinner fa-spin"></i> Loading...
            </div>
            <div v-else>
              <div
                v-for="(item, index) in balances"
                :key="item.code"
                :class="{ 'balance-row-divider': index > 0 }"
                class="balance-row"
              >
                <span class="balance-country-label">{{ item.country }}</span>
                <span
                  v-if="item.success && item.balance"
                  class="balance-value"
                  :class="parseFloat(item.balance.split(' ')[1]) < 1000 ? 'balance-low' : 'balance-ok'"
                >
                  {{ formatBalanceFull(item.balance) }}
                </span>
                <span v-else class="balance-na" :title="item.message">N/A</span>
              </div>
              <div v-if="!balances.length" class="balance-na">No countries assigned</div>
            </div>
            <p class="balance-card-title">SMS Balance</p>
          </div>
          <div class="icon">
            <i class="fas fa-credit-card"></i>
          </div>
          <a href="#" class="small-box-footer" @click.prevent="refreshAll">
            Refresh <i class="fas fa-sync-alt"></i>
          </a>
        </div>
      </div>
    </div>

    <!-- Charts Row -->
    <div class="row mb-4">
      <div class="col-md-6">
        <ChartBox
          title="Delivery Statistics"
          chart-id="deliveryChart"
          :chart-data="deliveryChartData"
          :chart-options="chartOptions"
          :is-loading="isStatsLoading"
        />
      </div>
      <div class="col-md-6">
        <ChartBox
          title="Monthly SMS Volume"
          chart-id="monthlyChart"
          :chart-data="monthlyChartData"
          :chart-options="chartOptions"
          :is-loading="isMonthlyStatsLoading"
        />
      </div>
    </div>

    <!-- Quick Actions -->
    <!-- <div class="row mb-4">
      <div class="col-12">
        <div class="card">
          <div class="card-header d-flex align-items-center">
            <h3 class="card-title mb-0">Quick Actions</h3>
            <button
              @click="refreshAll"
              class="btn btn-sm btn-outline-primary ml-auto"
            >
              <i class="fas fa-sync-alt mr-1"></i> Refresh All
            </button>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-3 col-sm-6 mb-3">
                <Link
                  :href="route('sms.create')"
                  class="btn btn-lg btn-block btn-outline-primary"
                >
                  <i
                    class="fas fa-paper-plane mb-2 d-block"
                    style="font-size: 24px"
                  ></i>
                  Send SMS
                </Link>
              </div>
              <div class="col-md-3 col-sm-6 mb-3">
                <Link href="#" class="btn btn-lg btn-block btn-outline-info">
                  <i
                    class="fas fa-users mb-2 d-block"
                    style="font-size: 24px"
                  ></i>
                  Add Recipients
                </Link>
              </div>
              <div class="col-md-3 col-sm-6 mb-3">
                <Link href="#" class="btn btn-lg btn-block btn-outline-success">
                  <i
                    class="fas fa-file-alt mb-2 d-block"
                    style="font-size: 24px"
                  ></i>
                  Templates
                </Link>
              </div>
              <div class="col-md-3 col-sm-6 mb-3">
                <Link
                  href="#"
                  class="btn btn-lg btn-block btn-outline-secondary"
                >
                  <i
                    class="fas fa-chart-line mb-2 d-block"
                    style="font-size: 24px"
                  ></i>
                  Reports
                </Link>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div> -->

    <!-- Recent Messages -->
    <!-- Recent Messages -->
    <div class="row">
      <div class="col-md-12">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Recent SMS Messages</h3>
          </div>
          <div class="card-body">
            <div v-if="isRecentSmsLoading" class="text-center py-4">
              <i class="fas fa-spinner fa-spin fa-2x"></i>
            </div>
            <div v-else class="table-responsive">
              <table class="table table-hover">
                <thead>
                  <tr>
                    <th>Title</th>
                    <th>Created By</th>
                    <th>Created At</th>
                    <th>Status</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="sms in recentSms" :key="sms.id">
                    <td>
                      <Link :href="`/sms/${sms.id}`">
                        <b>{{ sms.text_title }}</b>
                      </Link>
                    </td>
                    <td>{{ sms.creator?.name || "N/A" }}</td>
                    <td>{{ formatDate(sms.created_at) }}</td>
                    <td>
                      <span
                        class="badge rounded-pill px-3 py-1"
                        :class="`bg-${
                          sms.status?.color_code?.toLowerCase() || 'secondary'
                        }`"
                      >
                        {{ sms.status?.text_status_name || "N/A" }}
                      </span>
                    </td>
                    <td>
                      <Link
                        :href="`/sms/${sms.id}`"
                        class="btn btn-sm btn-primary"
                      >
                        <i class="fas fa-eye"></i> View
                      </Link>
                    </td>
                  </tr>
                  <tr v-if="recentSms.length === 0">
                    <td colspan="5" class="text-center">
                      No recent messages found
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

<style scoped>
.alert {
  border-radius: 8px;
  padding: 12px 16px;
}

.card {
  border-radius: 10px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.btn-lg {
  padding: 12px;
  font-size: 16px;
}

.btn-block {
  display: flex;
  flex-direction: column;
  align-items: center;
  height: 100%;
}

.badge {
  padding: 0.35em 0.65em;
  font-size: 0.75em;
  font-weight: 700;
  line-height: 1;
  color: #fff;
  text-align: center;
  white-space: nowrap;
  vertical-align: baseline;
  border-radius: 0.25rem;
}

.table-responsive {
  overflow-x: auto;
}

.table {
  width: 100%;
  margin-bottom: 1rem;
  color: #212529;
}

.table th,
.table td {
  padding: 0.75rem;
  vertical-align: top;
  border-top: 1px solid #dee2e6;
}

.table thead th {
  vertical-align: bottom;
  border-bottom: 2px solid #dee2e6;
}

.table-hover tbody tr:hover {
  color: #212529;
  background-color: rgba(0, 0, 0, 0.075);
}

/* Balance card — neutral/white, no coloured bg */
.balance-box {
  background: #fff;
  color: #343a40;
  border: 1px solid #dee2e6;
  box-shadow: 0 1px 4px rgba(0,0,0,0.06);
}
.balance-box .icon i {
  color: #ced4da;
}
.balance-box .small-box-footer {
  background: rgba(0,0,0,0.04);
  color: #6c757d;
}
.balance-box .small-box-footer:hover {
  background: rgba(0,0,0,0.08);
  color: #343a40;
}
.balance-card-title {
  font-size: 0.85rem;
  color: #6c757d;
  margin: 6px 0 0;
}
.balance-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  line-height: 1.4;
}
.balance-row-divider {
  border-top: 1px solid #f0f0f0;
  margin-top: 4px;
  padding-top: 4px;
}
.balance-country-label {
  font-size: 0.78rem;
  color: #6c757d;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-right: 6px;
}
.balance-value {
  font-size: 1.05rem;
  font-weight: 700;
}
.balance-ok {
  color: #28a745;
}
.balance-low {
  color: #dc3545;
}
.balance-na {
  font-size: 0.9rem;
  color: #adb5bd;
}
.balance-loading {
  color: #6c757d;
  font-size: 0.9rem;
}
</style>