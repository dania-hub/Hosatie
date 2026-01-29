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
const quantitySort = ref("");
const expandedDrugId = ref(null);

const toggleDrug = (id) => {
    expandedDrugId.value = expandedDrugId.value === id ? null : id;
};

// ----------------------------------------------------
// 3. جلب البيانات
// ----------------------------------------------------
const fetchInventory = async () => {
    isLoading.value = true;
    error.value = null;
    try {
        // Pass type=supplier to the API
        const response = await api.get('/super-admin/inventory', {
            params: { type: 'supplier' }
        });
        // Handle standard Laravel resource response or direct array
        const data = response.data.data || response.data;
        inventories.value = Array.isArray(data) ? data : [];
    } catch (err) {
        console.error('فشل جلب مخزون الموردين:', err);
        error.value = "حدث خطأ أثناء تحميل بيانات مخزون الموردين.";
    } finally {
        isLoading.value = false;
    }
};

// ----------------------------------------------------
// 4. التصفية والبحث
// ----------------------------------------------------
const filteredInventories = computed(() => {
    let list = [...inventories.value];

    if (searchTerm.value && searchTerm.value.trim()) {
        const search = searchTerm.value.toLowerCase().trim();
        list = list.filter((item) => {
            const fields = [
                item.drug_name,
                item.strength,
            ];

            // Search in drug name and strength
            const mainMatch = fields.some((field) => {
                if (field === null || field === undefined) return false;
                return String(field).toLowerCase().includes(search);
            });

            if (mainMatch) return true;

            // Search in supplier names within details
            if (item.details && Array.isArray(item.details)) {
                return item.details.some(detail => 
                    detail.entity_name && detail.entity_name.toLowerCase().includes(search)
                );
            }

            return false;
        });
    }

    if (quantitySort.value) {
        list.sort((a, b) => {
            const qtyA = Number(a.total_current_quantity) || 0;
            const qtyB = Number(b.total_current_quantity) || 0;
            return quantitySort.value === 'asc' ? qtyA - qtyB : qtyB - qtyA;
        });
    }

    return list;
});

// ----------------------------------------------------
// 5. الطباعة
// ----------------------------------------------------
const printTable = () => {
    const resultsCount = filteredInventories.value.length;

    if (resultsCount === 0) {
        return;
    }

    const printWindow = window.open("", "_blank", "height=800,width=1000");

    if (!printWindow || printWindow.closed || typeof printWindow.closed === "undefined") {
        alert("❌ فشل عملية الطباعة. يرجى السماح بفتح النوافذ المنبثقة لهذا الموقع.");
        return;
    }

    const currentDate = new Date().toLocaleDateString('ar-LY', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        numberingSystem: 'latn'
    });

    let tableHtml = `
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>تقرير مخزون الموردين - ${currentDate}</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        @media print {
            @page { margin: 15mm; size: A4 orientation: landscape; }
            .no-print { display: none; }
        }
        
        * { box-sizing: border-box; font-family: 'Cairo', sans-serif; }
        body { padding: 0; margin: 0; color: #1e293b; background: white; line-height: 1.5; }
        
        .print-container { max-width: 1100px; margin: 0 auto; padding: 20px; }
        .page-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 30px; 
            padding-bottom: 20px; 
            border-bottom: 2px solid #2E5077;
        }
        
        .gov-title { text-align: right; }
        .gov-title h2 { margin: 0; font-size: 20px; font-weight: 800; color: #2E5077; }
        .gov-title p { margin: 5px 0; font-size: 14px; color: #64748b; }
        
        .report-title { text-align: center; margin: 20px 0; }
        .report-title h1 { 
            margin: 0; 
            font-size: 24px; 
            color: #1e293b; 
            background: #f1f5f9;
            display: inline-block;
            padding: 10px 40px;
            border-radius: 50px;
        }
        
        .summary-box {
            display: grid;
            grid-template-cols: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
            background: #f8fafc;
            padding: 15px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }
        
        .stat-item { display: flex; flex-direction: column; }
        .stat-label { font-size: 11px; color: #64748b; font-weight: 600; margin-bottom: 4px; }
        .stat-value { font-size: 14px; color: #2E5077; font-weight: 700; }

        table { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 10px; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
        th { 
            background-color: #2E5077; 
            color: white; 
            font-weight: 700; 
            padding: 12px 10px; 
            text-align: right; 
            font-size: 12px;
        }
        td { 
            padding: 10px; 
            border-bottom: 1px solid #f1f5f9; 
            font-size: 11px; 
            color: #334155;
            vertical-align: middle;
        }
        tr:last-child td { border-bottom: none; }
        tr:nth-child(even) { background-color: #f8fafc; }
        
        .qty-badge {
            font-weight: 700;
            color: #2E5077;
        }
        
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            color: #64748b;
        }
        .signature { text-align: center; margin-top: 40px; }
    </style>
</head>
<body>
    <div class="print-container">
        <div class="page-header">
            <div class="gov-title">
                <h2>وزارة الصحـــــة   </h2>
                <p>إدارة الشؤون الطبية والخدمات</p>
                <p>قسم متابعة المخازن الطبية</p>
            </div>
            <div style="text-align: left;">
                <p style="margin: 0; font-weight: 700; color: #2E5077;">تاريخ التقرير: ${currentDate}</p>
                <p style="margin: 5px 0; color: #64748b; font-size: 12px;">رمز التقرير: SUPPLIER/INV/${new Date().getTime().toString().slice(-6)}</p>
            </div>
        </div>

        <div class="report-title">
            <h1>تقرير مخزون شركات التوريد (المخازن)</h1>
        </div>

        <div class="summary-box">
            <div class="stat-item">
                <span class="stat-label">إجمالي الأصناف</span>
                <span class="stat-value">${resultsCount} صنف دوائي</span>
            </div>
            <div class="stat-item">
                <span class="stat-label">حالة التقرير</span>
                <span class="stat-value">مخزون فعلي </span>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th width="25%">اسم الدواء</th>
                    <th width="10%">التركيز</th>
                    <th width="20%">الكمية المتوفرة</th>
                    <th width="20%">إجمالي الطلبات</th>
                    <th width="25%">الكمية المحتاجة</th>
                </tr>
            </thead>
            <tbody>
    `;

    filteredInventories.value.forEach((drug) => {
        const boxLabel = drug.unit === 'مل' ? 'عبوة' : 'علبة';
        const unitLabel = drug.unit === 'مل' ? 'مل' : 'حبة';
        
        // --- Main Drug Row Quantities ---
        let totalQtyDisplay = "";
        if (drug.units_per_box > 1) {
            totalQtyDisplay = `${drug.total_current_boxes} ${boxLabel}`;
            if (drug.total_current_remainder > 0) {
                totalQtyDisplay += ` و ${drug.total_current_remainder} ${unitLabel}`;
            }
        } else {
            totalQtyDisplay = `${drug.total_current_quantity} ${drug.unit}`;
        }

        let totalRequestedDisplay = "";
        if (drug.units_per_box > 1) {
            totalRequestedDisplay = `${drug.total_requested_boxes} ${boxLabel}`;
            if (drug.total_requested_remainder > 0) {
                totalRequestedDisplay += ` و ${drug.total_requested_remainder} ${unitLabel}`;
            }
        } else {
            totalRequestedDisplay = `${drug.total_requested_quantity} ${drug.unit}`;
        }

        let totalRequiredDisplay = "";
        if (drug.units_per_box > 1) {
            totalRequiredDisplay = `${drug.total_required_boxes} ${boxLabel}`;
            if (drug.total_required_remainder > 0) {
                totalRequiredDisplay += ` و ${drug.total_required_remainder} ${unitLabel}`;
            }
        } else {
            totalRequiredDisplay = `${drug.total_required_quantity} ${drug.unit}`;
        }

        // Main Row for the drug
        tableHtml += `
            <tr style="background-color: #f1f5f9; font-weight: 800;">
                <td>${drug.drug_name}</td>
                <td>${drug.strength}</td>
                <td class="qty-badge">${totalQtyDisplay}</td>
                <td>${totalRequestedDisplay}</td>
                <td>${totalRequiredDisplay}</td>
            </tr>
        `;

        // Details for each warehouse
        if (drug.details && drug.details.length > 0) {
            drug.details.forEach(detail => {
                let detailQty = "";
                if (drug.units_per_box > 1) {
                    detailQty = `${detail.current_quantity_boxes} ${boxLabel}`;
                    if (detail.current_quantity_remainder > 0) {
                        detailQty += ` و ${detail.current_quantity_remainder} ${unitLabel}`;
                    }
                } else {
                    detailQty = `${detail.current_quantity} ${drug.unit}`;
                }

                let detailRequested = "";
                if (drug.units_per_box > 1) {
                    detailRequested = `${detail.total_requested_boxes} ${boxLabel}`;
                    if (detail.total_requested_remainder > 0) {
                        detailRequested += ` و ${detail.total_requested_remainder} ${unitLabel}`;
                    }
                } else {
                    detailRequested = `${detail.total_requested} ${drug.unit}`;
                }

                let detailReq = "";
                if (detail.required_quantity > 0) {
                    if (drug.units_per_box > 1) {
                        detailReq = `${detail.required_quantity_boxes} ${boxLabel}`;
                        if (detail.required_quantity_remainder > 0) {
                            detailReq += ` و ${detail.required_quantity_remainder} ${unitLabel}`;
                        }
                    } else {
                        detailReq = `${detail.required_quantity} ${drug.unit}`;
                    }
                } else {
                    detailReq = "يكفي";
                }

                tableHtml += `
                    <tr>
                        <td colspan="2" style="padding-right: 30px; font-size: 10px; color: #64748b;">
                            <span style="margin-left: 5px;">📍</span> ${detail.entity_name}
                        </td>
                        <td style="font-size: 10px;">${detailQty}</td>
                        <td style="font-size: 10px;">${detailRequested}</td>
                        <td style="font-size: 10px; font-weight: 600;">
                            ${detailReq}
                        </td>
                    </tr>
                `;
            });
        }
    });

    tableHtml += `
            </tbody>
        </table>

        <div class="footer">
            <p>تم استخراج هذا التقرير آلياً من المنظومة المركزية   </p>
            <p>نهاية التقرير</p>
        </div>

        <div class="signature">
            <p style="font-weight: 700; margin-bottom: 50px;">اعتماد مدير الإدارة  </p>
            <p>.......................................</p>
        </div>
    </div>
</body>
</html>
    `;

    printWindow.document.write(tableHtml);
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
                    <search v-model="searchTerm" placeholder="ابحث بجميع المعلومات ..." class="flex-1 min-w-[150px] sm:min-w-[200px]" />
                    <div class="dropdown dropdown-start">
                        <div
                            tabindex="0"
                            role="button"
                            class="inline-flex items-center px-4 py-3 border-2 border-[#ffffff8d] rounded-full bg-[#4DA1A9] text-white text-sm font-medium cursor-pointer hover:bg-[#5e8c90f9] transition-all duration-200"
                        >
                            <Icon icon="lucide:arrow-down-up" class="w-5 h-5 ml-2" />
                            فرز الكمية
                        </div>
                        <ul
                            tabindex="0"
                            class="dropdown-content z-50 menu p-2 shadow-lg bg-white border rounded-2xl w-52 text-right"
                        >
                            <li>
                                <a
                                    @click="quantitySort = ''"
                                    :class="{'font-bold text-[#4DA1A9]': quantitySort === ''}"
                                >
                                    بدون فرز
                                </a>
                            </li>
                            <li>
                                <a
                                    @click="quantitySort = 'desc'"
                                    :class="{'font-bold text-[#4DA1A9]': quantitySort === 'desc'}"
                                >
                                    الكمية الأعلى احتياج
                                </a>
                            </li>
                            <li>
                                <a
                                    @click="quantitySort = 'asc'"
                                    :class="{'font-bold text-[#4DA1A9]': quantitySort === 'asc'}"
                                >
                                    الكمية الأقل احتياج
                                </a>
                            </li>
                        </ul>
                    </div>
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
                                    <th class="p-4 w-10"></th>
                                    <th class="p-4">اسم الدواء</th>
                                    <th class="p-4">التركيز</th>
                                    <th class="p-4">إجمالي الكمية الحالية</th>
                                    <th class="p-4">إجمالي الكمية المحتاجة</th>
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
                                    <template v-for="drug in filteredInventories" :key="drug.id">
                                        <tr 
                                            class="hover:bg-gray-50 border-b border-gray-200 cursor-pointer transition-colors"
                                            @click="toggleDrug(drug.id)"
                                            :class="{'bg-[#f8fafc]': expandedDrugId === drug.id}"
                                        >
                                            <td class="p-4 text-center">
                                                <Icon 
                                                    :icon="expandedDrugId === drug.id ? 'solar:alt-arrow-down-bold-duotone' : 'solar:alt-arrow-left-bold-duotone'" 
                                                    class="w-5 h-5 text-[#4DA1A9] transition-transform duration-300"
                                                />
                                            </td>
                                            <td class="p-4 font-bold">{{ drug.drug_name }}</td>
                                            <td class="p-4 text-gray-600">{{ drug.strength }}</td>
                                            <td class="p-4 font-bold text-blue-600">
                                                <div v-if="drug.units_per_box > 1">
                                                    {{ drug.total_current_boxes }} {{ drug.unit === 'مل' ? 'عبوة' : 'علبة' }}
                                                    <span v-if="drug.total_current_remainder > 0" class="text-xs text-gray-400 font-normal">
                                                        و {{ drug.total_current_remainder }} {{ drug.unit === 'مل' ? 'مل' : 'حبة' }}
                                                    </span>
                                                </div>
                                                <div v-else>
                                                    {{ drug.total_current_quantity }} {{ drug.unit }}
                                                </div>
                                            </td>
                                            <td class="p-4 font-bold text-red-600">
                                                <div v-if="drug.units_per_box > 1">
                                                    {{ drug.total_required_boxes }} {{ drug.unit === 'مل' ? 'عبوة' : 'علبة' }}
                                                    <span v-if="drug.total_required_remainder > 0" class="text-xs text-red-400 font-normal">
                                                        و {{ drug.total_required_remainder }} {{ drug.unit === 'مل' ? 'مل' : 'حبة' }}
                                                    </span>
                                                </div>
                                                <div v-else>
                                                    {{ drug.total_required_quantity }} {{ drug.unit }}
                                                </div>
                                            </td>
                                        </tr>
                                        
                                        <!-- تفاصيل المخازن -->
                                        <tr v-if="expandedDrugId === drug.id">
                                            <td colspan="5" class="p-0 bg-gray-50/50">
                                                <div class="px-12 py-4 border-b border-gray-100">
                                                    <table class="w-full text-sm">
                                                        <thead class="text-gray-500 border-b border-gray-200">
                                                            <tr>
                                                                <th class="py-2 text-right">المخزن (المورد)</th>
                                                                <th class="py-2 text-right">الكمية المتوفرة</th>
                                                                <th class="py-2 text-right">إجمالي الطلبات</th>
                                                                <th class="py-2 text-right">الكمية المحتاجة</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr v-for="detail in drug.details" :key="detail.entity_id" class="border-b border-gray-100 last:border-0 hover:bg-white">
                                                                <td class="py-3 font-medium text-gray-700">{{ detail.entity_name }}</td>
                                                                <td class="py-3">
                                                                    <span v-if="drug.units_per_box > 1">
                                                                        {{ detail.current_quantity_boxes }} {{ drug.unit === 'مل' ? 'عبوة' : 'علبة' }}
                                                                        <span v-if="detail.current_quantity_remainder > 0" class="text-xs text-gray-400">
                                                                            +{{ detail.current_quantity_remainder }}
                                                                        </span>
                                                                    </span>
                                                                    <span v-else>{{ detail.current_quantity }} {{ drug.unit }}</span>
                                                                </td>
                                                                <td class="py-3 text-gray-500">
                                                                    <span v-if="drug.units_per_box > 1">
                                                                        {{ detail.total_requested_boxes }} {{ drug.unit === 'مل' ? 'عبوة' : 'علبة' }}
                                                                        <span v-if="detail.total_requested_remainder > 0" class="text-xs">
                                                                            +{{ detail.total_requested_remainder }}
                                                                        </span>
                                                                    </span>
                                                                    <span v-else>{{ detail.total_requested }} {{ drug.unit }}</span>
                                                                </td>
                                                                <td class="py-3">
                                                                    <span v-if="detail.required_quantity > 0" class="px-2 py-1 bg-red-100 text-red-700 rounded-lg font-bold">
                                                                        <span v-if="drug.units_per_box > 1">
                                                                            {{ detail.required_quantity_boxes }} {{ drug.unit === 'مل' ? 'عبوة' : 'علبة' }}
                                                                            <span v-if="detail.required_quantity_remainder > 0" class="text-xs">
                                                                                +{{ detail.required_quantity_remainder }}
                                                                            </span>
                                                                        </span>
                                                                        <span v-else>{{ detail.required_quantity }} {{ drug.unit }}</span>
                                                                    </span>
                                                                    <span v-else class="text-green-600">يكفي</span>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                    <tr v-if="filteredInventories.length === 0">
                                        <td colspan="5" class="py-12"><EmptyState message="لا توجد بيانات مخزون للموردين" /></td>
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
