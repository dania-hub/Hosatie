<script setup>
import DefaultLayout from "@/components/DefaultLayout.vue";
import TableSkeleton from "@/components/Shared/TableSkeleton.vue";
import ErrorState from "@/components/Shared/ErrorState.vue";
import EmptyState from "@/components/Shared/EmptyState.vue";
import search from "@/components/search.vue";
import btnprint from "@/components/btnprint.vue";
import { ref, computed, onMounted } from "vue";
import { Icon } from "@iconify/vue";
import axios from "axios";

// ----------------------------------------------------
// 1. تكوين Axios
// ----------------------------------------------------
const api = axios.create({
    baseURL: '/api',
    timeout: 30000,
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    }
});

api.interceptors.request.use(
    (config) => {
        const token = localStorage.getItem('auth_token') || localStorage.getItem('token');
        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }
        return config;
    },
    (error) => Promise.reject(error)
);

api.interceptors.response.use(
    (response) => response,
    (error) => Promise.reject(error)
);

// ----------------------------------------------------
// 2. البيانات
// ----------------------------------------------------
const inventories = ref([]);
const isLoading = ref(true);
const error = ref(null);
const searchTerm = ref("");

// ----------------------------------------------------
// 3. جلب البيانات
// ----------------------------------------------------
const fetchInventory = async () => {
    isLoading.value = true;
    error.value = null;
    try {
        const response = await api.get('/super-admin/inventory');
        // Handle standard Laravel resource response or direct array
        const data = response.data.data || response.data;
        inventories.value = Array.isArray(data) ? data : [];
    } catch (err) {
        console.error('فشل جلب المخزون:', err);
        error.value = "حدث خطأ أثناء تحميل بيانات المخزون.";
    } finally {
        isLoading.value = false;
    }
};

// ----------------------------------------------------
// 4. التصفية والبحث
// ----------------------------------------------------
const filteredInventories = computed(() => {
    if (!searchTerm.value) return inventories.value;
    
    const search = searchTerm.value.toLowerCase();
    return inventories.value.filter(item => 
        (item.drug_name && item.drug_name.toLowerCase().includes(search)) ||
        (item.hospital_name && item.hospital_name.toLowerCase().includes(search))
    );
});

// ----------------------------------------------------
// 5. الطباعة
// ----------------------------------------------------
const printTable = () => {
    const printWindow = window.open('', '_blank', 'height=600,width=800');
    if (!printWindow) return;

    let tableHtml = `
        <style>
            body { font-family: 'Arial', sans-serif; direction: rtl; padding: 20px; }
            table { width: 100%; border-collapse: collapse; margin-top: 15px; }
            th, td { border: 1px solid #ccc; padding: 10px; text-align: right; }
            th { background-color: #f2f2f2; font-weight: bold; }
            h1 { text-align: center; color: #2E5077; margin-bottom: 10px; }
        </style>
        <h1>تقرير المخزون</h1>
        <table>
            <thead>
                <tr>
                    <th>اسم الدواء</th>
                    <th>التركيز</th>
                    <th>الكمية الحالية</th>
                    <th>الكمية المطلوبة</th>
                    <th>المستشفى</th>
                </tr>
            </thead>
            <tbody>
    `;

    filteredInventories.value.forEach(item => {
        tableHtml += `
            <tr>
                <td>${item.drug_name}</td>
                <td>${item.strength}</td>
                <td>${item.current_quantity}</td>
                <td>${item.needed_quantity}</td>
                <td>${item.hospital_name}</td>
            </tr>
        `;
    });

    tableHtml += `</tbody></table>`;

    printWindow.document.write('<html><head><title>طباعة المخزون</title></head><body>');
    printWindow.document.write(tableHtml);
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    printWindow.onload = () => {
        printWindow.focus();
        printWindow.print();
    };
};

onMounted(() => {
    fetchInventory();
});
</script>

<template>
    <DefaultLayout>
        <main class="flex-1 p-4 sm:p-5 pt-3">
            <div class="flex flex-col sm:flex-row justify-between items-center mb-4 gap-3">
                <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
                    <search v-model="searchTerm" class="flex-1 min-w-[150px] sm:min-w-[200px]" />
                    <p class="text-sm font-semibold text-gray-600 ml-2">
                        عدد النتائج: <span class="text-[#4DA1A9] text-lg font-bold">{{ filteredInventories.length }}</span>
                    </p>
                </div>
                <div class="flex items-center justify-end w-full sm:w-auto">
                    <btnprint @click="printTable" />
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow h-107 overflow-hidden flex flex-col">
                <div class="overflow-y-auto flex-1" style="scrollbar-width: auto; scrollbar-color: grey transparent; direction: ltr;">
                    <div class="overflow-x-auto h-full">
                        <table dir="rtl" class="table w-full text-right min-w-[800px] border-collapse">
                            <thead class="bg-[#9aced2] text-black sticky top-0 z-10 border-b border-gray-300">
                                <tr>
                                    <th class="p-4">اسم الدواء</th>
                                    <th class="p-4">التركيز</th>
                                    <th class="p-4">الكمية الحالية</th>
                                    <th class="p-4">الكمية المطلوبة</th>
                                    <th class="p-4">المستشفى</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-800">
                                <tr v-if="isLoading">
                                    <td colspan="5" class="p-4"><TableSkeleton :rows="5" /></td>
                                </tr>
                                <tr v-else-if="error">
                                    <td colspan="5" class="py-12"><ErrorState :message="error" :retry="fetchInventory" /></td>
                                </tr>
                                <template v-else>
                                    <tr v-for="item in filteredInventories" :key="item.id" class="hover:bg-gray-100 border border-gray-300">
                                        <td class="p-4">{{ item.drug_name }}</td>
                                        <td class="p-4">{{ item.strength }}</td>
                                        <td class="p-4 font-bold text-blue-600">{{ item.current_quantity }}</td>
                                        <td class="p-4 font-bold text-red-600">{{ item.needed_quantity }}</td>
                                        <td class="p-4">{{ item.hospital_name }}</td>
                                    </tr>
                                    <tr v-if="filteredInventories.length === 0">
                                        <td colspan="5" class="py-12"><EmptyState message="لا توجد بيانات مخزون" /></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </DefaultLayout>
</template>
