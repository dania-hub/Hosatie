<script setup>
import { ref, onMounted, watch, computed } from "vue";
import axios from 'axios';
import { Icon } from "@iconify/vue";
import LoadingState from "@/components/Shared/LoadingState.vue";
import ErrorState from "@/components/Shared/ErrorState.vue";
import EmptyState from "@/components/Shared/EmptyState.vue";
import DefaultLayout from "@/components/DefaultLayout.vue"; 
import Toast from "@/components/Shared/Toast.vue"; 

// ----------------------------------------------------
// 1. إعداد API
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
  config => {
    const token = localStorage.getItem('auth_token') || localStorage.getItem('token');
    if (token) config.headers.Authorization = `Bearer ${token}`;
    return config;
  },
  error => Promise.reject(error)
);

// ----------------------------------------------------
// 2. الحالة (State)
// ----------------------------------------------------
const toast = ref({
    show: false,
    type: 'success',
    title: '',
    message: ''
});

const showToast = (type, title, message) => {
    toast.value = { show: true, type, title, message };
};

const currentTab = ref('hospitals');
const isLoading = ref(false);
const error = ref(null);
const reportData = ref([]);

// تفاصيل الطلبات الشهرية
const viewMode = ref('list'); // 'list' | 'details'
const selectedMonthData = ref(null);
const monthDetails = ref([]);
const isDetailLoading = ref(false);

// نافذة العناصر (Items Modal)
const isItemsModalOpen = ref(false);
const itemsData = ref([]);
const isItemsLoading = ref(false);
const selectedRequestForItems = ref(null);

// تفاصيل النشاط (Activity Modal)
const isActivityModalOpen = ref(false);
const selectedActivity = ref(null);

// قاموس الأدوية للترجمة
const drugsMap = ref({});
const fetchDrugs = async () => {
    try {
        const response = await api.get('/super-admin/drugs');
        const drugs = response.data.data || response.data || [];
        const map = {};
        drugs.forEach(d => {
            map[d.id] = d.drug_name || d.name || d.name_ar;
        });
        drugsMap.value = map;
    } catch (e) {
        console.error('Failed to fetch drugs map:', e);
    }
};

// الطباعة
const isPrinting = ref(false);
const printProgress = ref('');

// تعريف التبويبات
const tabs = [
    { id: 'hospitals', name: 'المؤسسات الصحية', icon: 'solar:hospital-bold-duotone' },
    { id: 'dispensings', name: 'عمليات الصرف', icon: 'solar:pill-bold-duotone' },
    { id: 'requests', name: 'الطلبات الشهرية', icon: 'solar:chart-2-bold-duotone' },
    { id: 'activities', name: 'سجل النشاطات', icon: 'solar:history-bold-duotone' }
];

// ----------------------------------------------------
// 3. جلب البيانات
// ----------------------------------------------------
const fetchReport = async () => {
    isLoading.value = true;
    error.value = null;
    reportData.value = [];

    const endpoints = {
        hospitals: '/super-admin/reports/hospitals',
        dispensings: '/super-admin/reports/dispensings',
        requests: '/super-admin/reports/requests-monthly',
        activities: '/super-admin/reports/activities'
    };

    try {
        const response = await api.get(endpoints[currentTab.value]);
        // الرد يأتي عادة في data.data
        reportData.value = response.data.data || response.data || [];
        
    } catch (err) {
        console.error(`Error fetching ${currentTab.value} report:`, err);
        error.value = err.response?.data?.message || 'فشل تحميل التقرير';
    } finally {
        isLoading.value = false;
    }
};

const showMonthDetails = async (monthItem) => {
    selectedMonthData.value = monthItem;
    viewMode.value = 'details';
    monthDetails.value = [];
    isDetailLoading.value = true;
    
    try {
        const response = await api.get('/super-admin/reports/requests-monthly/details', {
            params: { month: monthItem.month }
        });
        monthDetails.value = response.data.data || [];
    } catch (err) {
        console.error('Error fetching month details:', err);
        // يمكن إضافة تنبيه هنا
    } finally {
        isDetailLoading.value = false;
    }
};

const openItemsModal = async (req) => {
    isItemsModalOpen.value = true;
    selectedRequestForItems.value = req;
    itemsData.value = [];
    isItemsLoading.value = true;

    try {
        const response = await api.get('/super-admin/reports/request-items', {
            params: {
                type: req.type,
                id: req.id
            }
        });
        itemsData.value = response.data.data || [];
    } catch (err) {
        console.error('Error fetching items:', err);
    } finally {
        isItemsLoading.value = false;
    }
};

const closeItemsModal = () => {
    isItemsModalOpen.value = false;
    selectedRequestForItems.value = null;
    itemsData.value = [];
};

const openActivityModal = (log) => {
    selectedActivity.value = log;
    isActivityModalOpen.value = true;
};

const closeActivityModal = () => {
    isActivityModalOpen.value = false;
    selectedActivity.value = null;
};

const backToMonths = () => {
    viewMode.value = 'list';
    selectedMonthData.value = null;
    monthDetails.value = [];
};

/* ----------------------------------------------------------------
   Departments Modal Logic
---------------------------------------------------------------- */
const isDepartmentsModalOpen = ref(false);
const hospitalDepartments = ref([]);
const isDepartmentsLoading = ref(false);
const selectedHospitalForDept = ref(null);

const openDepartmentsModal = async (hospital) => {
    isDepartmentsModalOpen.value = true;
    selectedHospitalForDept.value = hospital;
    hospitalDepartments.value = [];
    isDepartmentsLoading.value = true;

    try {
        const response = await api.get('/super-admin/reports/hospital-departments', {
            params: { hospital_id: hospital.id }
        });
        hospitalDepartments.value = response.data.data || [];
    } catch (err) {
        console.error('Error fetching departments:', err);
    } finally {
        isDepartmentsLoading.value = false;
    }
};

const closeDepartmentsModal = () => {
    isDepartmentsModalOpen.value = false;
    selectedHospitalForDept.value = null;
    hospitalDepartments.value = [];
};

/* ----------------------------------------------------------------
   Pharmacies Modal Logic
---------------------------------------------------------------- */
const isPharmaciesModalOpen = ref(false);
const hospitalPharmacies = ref([]);
const isPharmaciesLoading = ref(false);
const selectedHospitalForPharm = ref(null);

const openPharmaciesModal = async (hospital) => {
    isPharmaciesModalOpen.value = true;
    selectedHospitalForPharm.value = hospital;
    hospitalPharmacies.value = [];
    isPharmaciesLoading.value = true;

    try {
        const response = await api.get('/super-admin/reports/hospital-pharmacies', {
            params: { hospital_id: hospital.id }
        });
        hospitalPharmacies.value = response.data.data || [];
    } catch (err) {
        console.error('Error fetching pharmacies:', err);
    } finally {
        isPharmaciesLoading.value = false;
    }
};

const closePharmaciesModal = () => {
    isPharmaciesModalOpen.value = false;
    selectedHospitalForPharm.value = null;
    hospitalPharmacies.value = [];
};

/* ----------------------------------------------------------------
   5. Activity Log Helpers
---------------------------------------------------------------- */
const tableNameTranslations = {
    'users': 'المستخدمين',
    'hospitals': 'المؤسسات الصحية',
    'pharmacies': 'الصيدليات',
    'departments': 'الأقسام',
    'warehouses': 'المخازن',
    'drugs': 'الأدوية',
    'suppliers': 'الموردين',
    'prescriptions': 'الوصفات الطبية',
    'dispensings': 'عمليات الصرف',
    'internal_supply_requests': 'طلبات التوريد الداخلي',
    'external_supply_requests': 'طلبات التوريد الخارجي',
    'external_supply_request': 'طلب توريد خارجي',
    'patient_transfer_requests': 'طلبات نقل المرضى',
    'complaints': 'الشكاوى',
    'inventories': 'المخزون',
    'notifications': 'الإشعارات',
    'audit_logs': 'سجل النشاطات',
};

const formatTableName = (name) => {
    return tableNameTranslations[name] || name;
};

const escapeHtml = (value) => {
    if (value === null || value === undefined) return '';
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
};

const normalizeDetails = (details) => {
    if (!details) return null;
    if (typeof details === 'string') {
        try {
            return JSON.parse(details);
        } catch {
            return null;
        }
    }
    return details;
};

const buildActivityDetailsSection = (title, details, toneClass) => {
    if (!details || typeof details !== 'object') return '';
    const rows = Object.entries(details)
        .filter(([key]) => isVisibleField(key))
        .map(([key, value]) => {
            const label = escapeHtml(formatKey(key));
            const formatted = escapeHtml(formatValue(key, value));
            return `<div class="detail-row"><span class="detail-key">${label}</span><span class="detail-value">${formatted}</span></div>`;
        })
        .join('');

    if (!rows) return '';

    return `
        <div class="details-block ${toneClass}">
            <div class="details-title">${escapeHtml(title)}</div>
            <div class="details-body">${rows}</div>
        </div>
    `;
};

const buildActivitiesPrintHtml = (logs, meta) => {
    const metaInfo = [
        `التاريخ: ${meta.printedAt}`,
        `العدد الإجمالي: ${meta.recordCount} سجل`
    ];
    if (meta.dateRange) metaInfo.push(`الفترة: ${meta.dateRange}`);
    if (meta.queryText) metaInfo.push(`البحث: ${meta.queryText}`);

    const logsHtml = logs.map((log) => {
        const details = normalizeDetails(log.details) || {};
        const oldDetails = normalizeDetails(details.old);
        const newDetails = normalizeDetails(details.new);

        const detailSections = [
            buildActivityDetailsSection('القيم السابقة', oldDetails, 'old'),
            buildActivityDetailsSection('القيم الجديدة', newDetails, 'new')
        ].filter(Boolean);
        
        const detailsRow = detailSections.length
            ? `
                <tr class="details-row">
                    <td colspan="5">
                        <div class="details-grid">${detailSections.join('')}</div>
                    </td>
                </tr>
              `
            : '';

        const createdAt = log.createdAt || log.created_at || log.date || '';

        return `
            <tbody class="log-entry">
                <tr class="data-row">
                    <td class="col-user">${escapeHtml(log.userName || 'غير معروف')}</td>
                    <td class="col-type">${escapeHtml(log.userTypeArabic || log.userType || '-')}</td>
                    <td class="col-hosp">${escapeHtml(log.affiliation || '-')}</td>
                    <td class="col-act">${escapeHtml(log.actionArabic || log.action || '-')}</td>
                    <td class="col-date" dir="ltr">${escapeHtml(createdAt)}</td>
                </tr>
                ${detailsRow}
            </tbody>
        `;
    }).join('');

    return `
        <!doctype html>
        <html lang="ar" dir="rtl">
        <head>
            <meta charset="utf-8">
            <title>${escapeHtml(meta.title)}</title>
            <style>
                @page { size: A4 landscape; margin: 10mm; }
                * { box-sizing: border-box; }
                body { 
                    font-family: Arial, Tahoma, sans-serif; 
                    margin: 0; 
                    color: #1e293b; 
                    background: white;
                    line-height: 1.4;
                    direction: rtl;
                }
                
                table { 
                    width: 100%; 
                    border-collapse: collapse; 
                    font-size: 10pt; 
                    table-layout: fixed;
                }
                
                thead { display: table-header-group; }
                
                /* Repeating Header Row */
                .report-header-th {
                    padding: 0 !important;
                    border: none !important;
                    background: white !important;
                }
                
                .title-bar {
                    background: #4DA1A9;
                    color: white;
                    padding: 15px 20px;
                    border-radius: 8px 8px 0 0;
                    margin-bottom: 0;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    border: 1px solid #3d8188;
                }
                
                .title-bar h1 {
                    margin: 0;
                    font-size: 18pt;
                    font-weight: bold;
                }
                
                .title-meta {
                    font-size: 9pt;
                    text-align: left;
                    opacity: 0.9;
                }

                /* Column Headers */
                .column-headers th { 
                    background: #F8FAFC; 
                    color: #334155; 
                    font-weight: bold; 
                    border: 1px solid #e2e8f0; 
                    padding: 10px; 
                    text-align: right;
                    font-size: 10pt;
                }
                
                .col-user { width: 16%; }
                .col-type { width: 14%; }
                .col-hosp { width: 18%; }
                .col-act { width: 37%; }
                .col-date { width: 15%; }

                /* Rows */
                .log-entry { page-break-inside: avoid; }
                .data-row td { 
                    border: 1px solid #e2e8f0; 
                    padding: 8px 10px; 
                    vertical-align: middle;
                    font-weight: 500;
                    word-wrap: break-word;
                }
                .data-row td.col-act { color: #0f172a; font-weight: bold; }
                
                .details-row td { 
                    background: #f8fafc; 
                    border: 1px solid #e2e8f0;
                    padding: 12px; 
                    border-top: none;
                }
                
                .details-grid { 
                    display: grid; 
                    grid-template-columns: 1fr 1fr; 
                    gap: 15px; 
                }
                
                .details-block { 
                    border: 1px solid #e2e8f0; 
                    border-radius: 6px; 
                    background: white; 
                }
                .details-title { 
                    font-weight: bold; 
                    padding: 6px 10px; 
                    font-size: 9pt; 
                    border-bottom: 1px solid #e2e8f0;
                }
                .details-block.old .details-title { background: #fee2e2; color: #991b1b; }
                .details-block.new .details-title { background: #dcfce7; color: #166534; }
                
                .details-body { padding: 8px; }
                .detail-row { 
                    display: flex; 
                    justify-content: space-between; 
                    border-bottom: 1px solid #f1f5f9; 
                    padding: 3px 0; 
                }
                .detail-row:last-child { border-bottom: none; }
                .detail-key { color: #64748b; font-size: 8.5pt; font-weight: normal; }
                .detail-value { color: #1e293b; font-weight: bold; font-size: 8.5pt; max-width: 70%; word-break: break-all; }

                .details-empty { text-align: center; padding: 30px; color: #94a3b8; }
                
                @media print {
                    body { -webkit-print-color-adjust: exact; }
                    .title-bar { -webkit-print-color-adjust: exact; background-color: #4DA1A9 !important; color: white !important; }
                    @page { size: A4 landscape; margin: 10mm; }
                }
            </style>
        </head>
        <body>
            <table>
                <thead>
                    <tr>
                        <th colspan="5" class="report-header-th">
                            <div class="title-bar">
                                <h1>نظام حُصتي - ${escapeHtml(meta.title)}</h1>
                                <div class="title-meta">
                                    ${metaInfo.join(' | ')}
                                </div>
                            </div>
                        </th>
                    </tr>
                    <tr class="column-headers">
                        <th class="col-user">المستخدم</th>
                        <th class="col-type">نوع المستخدم</th>
                        <th class="col-hosp">يتبع</th>
                        <th class="col-act">العملية</th>
                        <th class="col-date">التاريخ</th>
                    </tr>
                </thead>
                ${logsHtml || `<tbody><tr><td colspan="5" class="details-empty">لا توجد سجلات لعرضها</td></tr></tbody>`}
            </table>
        </body>
        </html>
    `;
};

const printActivitiesReport = () => {
    const logs = isSelectionMode.value
        ? filteredReportData.value.filter(log => selectedIds.value.has(log.id))
        : filteredReportData.value;

    isPrinting.value = true;
    printProgress.value = 'جاري تجهيز سجل العمليات للطباعة...';

    const printWindow = window.open('', '_blank', 'height=900,width=1200');
    if (!printWindow || printWindow.closed || typeof printWindow.closed === 'undefined') {
        showToast('error', 'تعذر الطباعة', 'الرجاء السماح بفتح نافذة الطباعة ثم المحاولة مرة أخرى.');
        isPrinting.value = false;
        printProgress.value = '';
        return;
    }

    const now = new Date();
    const meta = {
        title: tabs.find(t => t.id === currentTab.value)?.name || 'سجل العمليات',
        printedAt: now.toLocaleString('ar-SA'),
        recordCount: logs.length,
        dateRange: dateFrom.value || dateTo.value
            ? `${dateFrom.value || 'غير محدد'} - ${dateTo.value || 'غير محدد'}`
            : '',
        queryText: searchQuery.value || '',
        selectedOnly: isSelectionMode.value
    };

    const html = buildActivitiesPrintHtml(logs, meta);
    printWindow.document.open();
    printWindow.document.write(html);
    printWindow.document.close();

    const finishPrinting = () => {
        isPrinting.value = false;
        printProgress.value = '';
    };

    printWindow.onload = () => {
        printWindow.focus();
        printWindow.print();
    };

    printWindow.onafterprint = () => {
        finishPrinting();
        printWindow.close();
    };
};

const printFullReport = async () => {
    if (isPrinting.value) return;
    
    // Validation if in selection mode
    if (isSelectionMode.value && selectedIds.value.size === 0) {
        showToast('warning', 'تنبيه', 'يرجى تحديد صف واحد على الأقل للطباعة');
        return;
    }

    if (currentTab.value === 'activities') {
        printActivitiesReport();
        return;
    }

    try {
        isPrinting.value = true;
        printProgress.value = 'جاري تحضير البيانات...';

        // Helper to check if we should process this item
        const shouldProcess = (item) => {
            if (!isSelectionMode.value) return true; // Print all
            return selectedIds.value.has(item.id);
        };

        // 1. توسيع بيانات المستشفيات
        if (currentTab.value === 'hospitals') {
            const total = reportData.value.length;
            for (let i = 0; i < total; i++) {
                const hospital = reportData.value[i];
                if (!shouldProcess(hospital)) continue;

                printProgress.value = `جاري تحميل بيانات المؤسسات (${i + 1}/${total})`;
                
                // تحميل الأقسام
                if (!hospital.fullDepartments) {
                    try {
                        const deptRes = await api.get('/super-admin/reports/hospital-departments', {
                            params: { hospital_id: hospital.id }
                        });
                        hospital.fullDepartments = deptRes.data.data || [];
                    } catch (e) {
                         console.error('Failed to load departments:', e);
                         hospital.fullDepartments = [];
                    }
                }

                // تحميل الصيدليات
                if (!hospital.fullPharmacies) {
                     try {
                        const pharmRes = await api.get('/super-admin/reports/hospital-pharmacies', {
                            params: { hospital_id: hospital.id }
                        });
                        hospital.fullPharmacies = pharmRes.data.data || [];
                    } catch (e) {
                         console.error('Failed to load pharmacies:', e);
                         hospital.fullPharmacies = [];
                    }
                }
            }
        }

        // 2. توسيع بيانات الطلبات (في وضع التفاصيل فقط)
        if (currentTab.value === 'requests' && viewMode.value === 'details') {
            const total = monthDetails.value.length;
            for (let i = 0; i < total; i++) {
                const req = monthDetails.value[i];
                if (!shouldProcess(req)) continue;

                printProgress.value = `جاري تحميل تفاصيل الطلبات (${i + 1}/${total})`;
                
                if (!req.fullItems) {
                    try {
                        const itemsRes = await api.get('/super-admin/reports/request-items', {
                            params: { type: req.type, id: req.id }
                        });
                        req.fullItems = itemsRes.data.data || [];
                        // ترجمة أسماء الأدوية
                        req.fullItems.forEach(item => {
                            if (item.drug_id && drugsMap.value[item.drug_id]) {
                                item.drug_name = drugsMap.value[item.drug_id];
                            }
                        });
                    } catch (e) {
                        console.error('Failed to load request items:', e);
                        req.fullItems = [];
                    }
                }
            }
        }

        // 3. توسيع بيانات العمليات للطباعة
        if (currentTab.value === 'dispensings') {
            const total = reportData.value.length;
            for (let i = 0; i < total; i++) {
                const dispensing = reportData.value[i];
                if (!shouldProcess(dispensing)) continue;

                printProgress.value = `جاري تحميل تفاصيل العمليات (${i + 1}/${total})`;
                
                if (!dispensing.fullDetails) {
                    try {
                        const detailRes = await api.get('/super-admin/reports/dispensing-details', {
                            params: { dispensing_id: dispensing.id }
                        });
                        dispensing.fullDetails = detailRes.data.data || [];
                        // ترجمة أسماء الأدوية
                        dispensing.fullDetails.forEach(detail => {
                            if (detail.drug_id && drugsMap.value[detail.drug_id]) {
                                detail.drug_name = drugsMap.value[detail.drug_id];
                            }
                        });
                    } catch (e) {
                        console.error('Failed to load dispensing details:', e);
                        dispensing.fullDetails = [];
                    }
                }
            }
        }

        // 4. توسيع بيانات النشاطات للطباعة (التفاصيل موجودة بالفعل)
        if (currentTab.value === 'activities') {
            // التفاصيل موجودة بالفعل في البيانات، لا نحتاج تحميل إضافي
            printProgress.value = 'جاري تحضير تفاصيل النشاطات...';
        }

        printProgress.value = 'جاري تهيئة التقرير للطباعة...';
        
        // إعطاء وقت للـ DOM ليتم تحديثه
        setTimeout(() => {
            // إضافة رأس التقرير للطباعة
            const printHeader = document.createElement('div');
            printHeader.id = 'print-header';
            const tabName = tabs.find(t => t.id === currentTab.value)?.name || 'التقرير';
            const recordCount = isSelectionMode.value ? selectedIds.value.size : 
                (currentTab.value === 'requests' && viewMode.value === 'details' ? filteredMonthDetails.value.length : filteredReportData.value.length);
            
            printHeader.innerHTML = `
                <div style="text-align: center; margin-bottom: 20px; border-bottom: 2px solid #4DA1A9; padding-bottom: 10px;">
                    <h1 style="color: #4DA1A9; font-size: 18pt; margin: 0; font-weight: bold;">تقرير ${tabName}</h1>
                    <p style="color: #666; font-size: 10pt; margin: 5px 0;">تاريخ التقرير: ${new Date().toLocaleDateString('ar-SA')} - ${new Date().toLocaleTimeString('ar-SA')}</p>
                    <p style="color: #666; font-size: 10pt; margin: 0;">عدد السجلات: ${recordCount}</p>
                    ${dateFrom.value || dateTo.value ? `<p style="color: #666; font-size: 9pt; margin: 2px 0;">الفترة الزمنية: ${dateFrom.value || 'غير محدد'} - ${dateTo.value || 'غير محدد'}</p>` : ''}
                </div>
            `;
            printHeader.style.cssText = 'display: none;';
            document.body.insertBefore(printHeader, document.body.firstChild);

            window.print();
            
            // إزالة رأس التقرير بعد الطباعة
            setTimeout(() => {
                const header = document.getElementById('print-header');
                if (header) header.remove();
            }, 1000);
            
            // إلغاء وضع الطباعة بعد الطباعة
             isPrinting.value = false;
        }, 500);

    } catch (err) {
        console.error('Print preparation error:', err);
        isPrinting.value = false;
        showToast('error', 'خطأ', 'حدث خطأ أثناء تحضير التقرير للطباعة');
    }
};

const fieldTranslations = {
    // الأساسيات (Common Fields)
    'id': 'المعرف',
    'uuid': 'المعرف الفريد',
    'name': 'الاسم',
    'name_ar': 'الاسم (عربي)',
    'name_en': 'الاسم (إنجليزي)',
    'full_name': 'الاسم الكامل',
    'first_name': 'الاسم الأول',
    'middle_name': 'الاسم الأوسط',
    'last_name': 'اسم العائلة',
    'username': 'اسم المستخدم',
    'userName': 'اسم المستخدم',
    'email': 'البريد الإلكتروني',
    'phone': 'رقم الهاتف',
    'phone_number': 'رقم الهاتف',
    'mobile': 'رقم الجوال',
    'mobile_number': 'رقم الجوال',
    'address': 'العنوان',
    'address_line_1': 'العنوان 1',
    'address_line_2': 'العنوان 2',
    'city': 'المدينة',
    'region': 'المنطقة',
    'country': 'الدولة',
    'postal_code': 'الرمز البريدي',
    'status': 'الحالة',
    'type': 'النوع',
    'userType': 'نوع المستخدم',
    'role': 'الدور الوظيفي',
    'password': 'كلمة المرور',
    'national_id': 'الرقم الوطني',
    'license_number': 'رقم الترخيص',
    'license_no': 'رقم الترخيص',
    'license_expiry': 'تاريخ انتهاء الترخيص',
    'description': 'الوصف',
    'notes': 'ملاحظات',
    'remarks': 'ملاحظات إضافية',
    'reason': 'السبب',
    'priority': 'الأولوية',
    'active': 'نشط',
    'is_active': 'نشط؟',
    'is_verified': 'مؤكد؟',
    
    // التواريخ (Dates & Timestamps)
    'created_at': 'تاريخ الإضافة',
    'updated_at': 'تاريخ التحديث',
    'deleted_at': 'تاريخ الحذف',
    'createdAt': 'تاريخ الإضافة',
    'updatedAt': 'تاريخ التحديث',
    'deletedAt': 'تاريخ الحذف',
    'dob': 'تاريخ الميلاد',
    'birth_date': 'تاريخ الميلاد',
    'expiry_date': 'تاريخ الصلاحية',
    'expiryDate': 'تاريخ الصلاحية',
    'delivery_date': 'تاريخ التسليم',
    'shipped_at': 'تاريخ الشحن',
    'approved_at': 'تاريخ الموافقة',
    'start_date': 'تاريخ البدء',
    'Start Date': 'تاريخ البدء',
    'startDate': 'تاريخ البدء',
    'end_date': 'تاريخ الانتهاء',
    'End Date': 'تاريخ الانتهاء',
    'endDate': 'تاريخ الانتهاء',
    'cancelled_at': 'تاريخ الإلغاء',
    'Cancelled At': 'تاريخ الإلغاء',
    'cancelledAt': 'تاريخ الإلغاء',
    'rejected_at': 'تاريخ الرفض',
    'rejectedAt': 'تاريخ الرفض',
    'Rejected At': 'تاريخ الرفض',
    'rejection_reason': 'سبب الرفض',
    'rejectionReason': 'سبب الرفض',
    'Rejection Reason': 'سبب الرفض',
    'rejected_by': 'تم الرفض بواسطة',
    'rejectedBy': 'تم الرفض بواسطة',
    'Rejected By': 'تم الرفض بواسطة',
    'approved_by': 'تمت الموافقة بواسطة',
    'approvedBy': 'تمت الموافقة بواسطة',
    'Approved By': 'تمت الموافقة بواسطة',
    'confirmed_at': 'تاريخ التأكيد',
    'confirmedAt': 'تاريخ التأكيد',
    'Confirmed At': 'تاريخ التأكيد',
    'confirmed_delivery': 'تأكيد التسليم',
    'confirmedDelivery': 'تأكيد التسليم',
    'Confirmed Delivery': 'تأكيد التسليم',
    'reply_message': 'رسالة الرد',
    'replyMessage': 'رسالة الرد',
    'Reply Message': 'رسالة الرد',
    'items_count': 'عدد العناصر',
    'itemsCount': 'عدد العناصر',
    'Items Count': 'عدد العناصر',
    
    // المؤسسات (Entities: Hospital, Pharmacy, Warehouse)
    'hospital_id': 'المؤسسة الصحية',
    'hospital_name': 'اسم المؤسسة',
    'hospitalName': 'اسم المؤسسة',
    'pharmacy_id': 'الصيدلية',
    'pharmacy_name': 'اسم الصيدلية',
    'pharmacyName': 'اسم الصيدلية',
    'pharmacy_code': 'كود الصيدلية',
    'warehouse_id': 'المخزن',
    'warehouse_name': 'اسم المخزن',
    'warehouseName': 'اسم المخزن',
    'warehouse_code': 'كود المخزن',
    'Warehouse Id': 'معرف المخزن',
    'Drug Id': 'معرف الدواء',
    'supplier_id': 'المورد',
    'supplier_name': 'اسم المورد',
    'supplierName': 'اسم المورد',
    'supplier_code': 'كود المورد',
    'department_id': 'القسم',
    'department_name': 'اسم القسم',
    'department': 'القسم',
    'prescription_id': 'معرف الوصفة',
    'prescriptionId': 'معرف الوصفة',
    'Prescription Id': 'معرف الوصفة',
    'item_count': 'عدد العناصر',
    'itemCount': 'عدد العناصر',
    'Item Count': 'عدد العناصر',
    
    // الأدوية (Drugs & Medical)
    'drug_id': 'الدواء',
    'drug_name': 'اسم الدواء',
    'drugName': 'اسم الدواء',
    'drugId': 'معرف الدواء',
    'Drug Id': 'معرف الدواء',
    'generic_name': 'الاسم العلمي',
    'genericName': 'الاسم العلمي',
    'strength': 'القوة/التركيز',
    'dosage_form': 'الشكل الدوائي',
    'form': 'الشكل الدوائي',
    'formulation': 'التركيبة',
    'unit': 'الوحدة',
    'category': 'الفئة العلاجية',
    'manufacturer': 'الشركة المصنعة',
    'origin_country': 'بلد المنشأ',
    'indications': 'دواعي الاستعمال',
    'warnings': 'تحذيرات',
    'contraindications': 'موانع الاستعمال',
    'side_effects': 'الأعراض الجانبية',
    'max_monthly_dose': 'أقصى جرعة شهرية',
    'maxMonthlyDose': 'أقصى جرعة شهرية',
    'units_per_box': 'عدد الوحدات في العلبة',
    'unitsPerBox': 'عدد الوحدات في العلبة',
    'utilization_type': 'نوع الاستخدام',
    'utilizationType': 'نوع الاستخدام',
    'is_controlled': 'خاضع للرقابة؟',
    'is_cold_chain': 'سلسلة تبريد؟',
    'is_emergency': 'طوارئ؟',
    'storage_requirements': 'شروط التخزين',
    'composition': 'التركيب',
    'dosage': 'الجرعة',
    'route': 'طريقة الاستعطاء',

    // المخزون والطلبات (Inventory, Stock & Requests)
    'qty': 'الكمية',
    'quantity': 'الكمية',
    'qty_received': 'الكمية المستلمة',
    'qty_shipped': 'الكمية الموفرة',
    'requested_qty': 'الكمية المطلوبة',
    'requestedQuantity': 'الكمية المطلوبة',
    'approved_qty': 'الكمية الموافق عليها',
    'approvedQuantity': 'الكمية الموافق عليها',
    'needed_qty': 'الكمية المحتاجة',
    'neededQuantity': 'الكمية المحتاجة',
    'stock': 'المخزون',
    'current_quantity': 'الكمية الحالية',
    'minimum_level': 'الحد الأدنى للمخزون',
    'refill_level': 'مستوى إعادة الطلب',
    'price': 'السعر',
    'cost': 'التكلفة',
    'batch_number': 'رقم التشغيلة',
    'batch_no': 'رقم التشغيلة',
    'serial_number': 'الرقم التسلسلي',
    'invoice_no': 'رقم الفاتورة',
    'request_id': 'رقم الطلب',
    'tracking_number': 'رقم التتبع',
    'shipment_id': 'رقم الشحنة',
    'daily_quantity': 'الكمية اليومية',
    'dailyQuantity': 'الكمية اليومية',
    'Daily Quantity': 'الكمية اليومية',
    'monthly_quantity': 'الكمية الشهرية',
    'monthlyQuantity': 'الكمية الشهرية',
    'Monthly Quantity': 'الكمية الشهرية',
    
    // المرضى والتشخيص (Patients & Diagnosis)
    'patient_id': 'المريض',
    'patient_name': 'اسم المريض',
    'gender': 'الجنس',
    'blood_type': 'فصيلة الدم',
    'blood_group': 'فصيلة الدم',
    'nationality': 'الجنسية',
    'occupation': 'المهنة',
    'marital_status': 'الحالة الاجتماعية',
    'diagnosis': 'التشخيص',
    'clinical_notes': 'ملاحظات سريرية',
    'file_number': 'رقم الملف',
    'insurance_id': 'رقم التأمين',
    'doctor_id': 'الطبيب',
    'doctor_name': 'اسم الطبيب',
    'doctor_info': 'بيانات الطبيب',
    'patient_info': 'بيانات المريض',
    
    // سجلات النظام (Activity Logs & System)
    'action': 'الإجراء',
    'ip_address': 'عنوان IP',
    'user_agent': 'متصفح المستخدم',
    'table_name': 'الجدول',
    'record_id': 'رقم السجل',
    'old_values': 'القيم السابقة',
    'new_values': 'القيم الجديدة',
    'manager_id': 'معرف المدير',
    'managerId': 'معرف المدير',
    'manager id': 'معرف المدير',
    'head_user_id': 'معرف المستخدم المسؤول',
    'headUserId': 'معرف المستخدم المسؤول',
    'Head User Id': 'معرف المستخدم المسؤول',
    'item': 'عنصر',
    'items': 'العناصر',
    'Item': 'عنصر',
    'Items': 'العناصر',
    'item_id': 'معرف العنصر',
    'itemId': 'معرف العنصر',
    'Item Id': 'معرف العنصر',
    'fulfilled_qty': 'الكمية الموفرة',
    'fulfilledQty': 'الكمية الموفرة',
    'Fulfilled Qty': 'الكمية الموفرة',
    'sentQuantity': 'الكمية المرسلة',
    'sent_quantity': 'الكمية المرسلة',
    'Sent Quantity': 'الكمية المرسلة',
    'receivedQuantity': 'الكمية المستلمة',
    'received_quantity': 'الكمية المستلمة',
    'Received Quantity': 'الكمية المستلمة',
    'drugs': 'الأدوية',
    'Drugs': 'الأدوية',
    'manager_name': 'اسم المدير',
    'managerName': 'اسم المدير',
    'Manager Name': 'اسم المدير',
    'metadata': 'بيانات إضافية',
    'Drug': 'الدواء',
    'Patient': 'المريض',
    'Hospital': 'المستشفى',
    'Pharmacy': 'الصيدلية',
    'Warehouse': 'المخزن',
    'User': 'المستخدم',
    'Doctor': 'الطبيب',
    'Supplier': 'المورد',
    'Prescription': 'الوصفة الطبية',
};

const valueTranslations = {
    'male': 'ذكر',
    'female': 'أنثى',
    'active': 'نشط',
    'inactive': 'غير نشط',
    'pending': 'قيد الانتظار',
    'approved': 'مقبول',
    'rejected': 'مرفوض',
    'completed': 'مكتمل',
    'cancelled': 'ملغى',
    'delivered': 'تم الاستلام',
    'fulfilled': 'مكتمل',
    'yes': 'نعم',
    'no': 'لا',
    'true': 'نعم',
    'false': 'لا',
    // Drug Forms
    'tablet': 'قرص',
    'capsule': 'كبسولة',
    'injection': 'حقنة',
    'syrup': 'شراب',
    'inhaler': 'بخاخ',
    'ointment': 'مرهم',
    'cream': 'كريم',
    'drops': 'قطرات',
    'vial': 'فيال',
    'ampoule': 'أمبول',
    // Categories
    'thyroid hormone': 'هرمون الغدة الدرقية',
    'antibiotic': 'مضاد حيوي',
    'analgesic': 'مسكن آلام',
    'antihypertensive': 'خافض لضغط الدم',
    'antidiabetic': 'مضاد للسكري',
    // Routes
    'oral': 'عن طريق الفم',
    'intravenous': 'وريدي (IV)',
    'intramuscular': 'عضلي (IM)',
    'topical': 'موضعي',
    'subcutaneous': 'تحت الجلد',
    'respiratory': 'تنفسي',
    // Roles & User Types
    'super_admin': 'مدير النظام',
    'hospital_admin': 'مدير مستشفى',
    'warehouse_manager': 'أمين مخزن',
    'pharmacist': 'صيدلي',
    'doctor': 'طبيب',
    'supplier': 'مورد',
    'data_entry': 'مدخل بيانات',
    'department_manager': 'مدير قسم',
    'staff': 'موظف',
    'storekeeper': 'أمين مخزن',
};

const toArabicNumerals = (str) => {
    if (str === null || str === undefined) return '';
    const arabicNumbers = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
    return String(str).replace(/[0-9]/g, (w) => arabicNumbers[parseInt(w)]);
};

const formatKey = (key) => {
    if (fieldTranslations[key]) return fieldTranslations[key];
    
    // Humanize if no translation
    return key.replace(/_/g, ' ')
              .replace(/([A-Z])/g, ' $1')
              .trim()
              .replace(/\b\w/g, l => l.toUpperCase());
};

const formatValue = (key, value) => {
    if (value === null || value === undefined || value === '') return '-';
    if (key === 'password') return '********';

    // Handle Objects & JSON strings
    if (typeof value === 'object' || (typeof value === 'string' && (value.trim().startsWith('{') || value.trim().startsWith('[')))) {
        try {
            const obj = typeof value === 'string' ? JSON.parse(value) : value;
            
            // 1. Specific Handlers
            const lowerKey = key.toLowerCase();
            const isEntityKey = lowerKey.includes('drug') || 
                               lowerKey.includes('patient') || 
                               lowerKey.includes('hospital') || 
                               lowerKey.includes('pharmacy') || 
                               lowerKey.includes('warehouse') || 
                               lowerKey.includes('doctor') ||
                               lowerKey.includes('prescription') ||
                               lowerKey.includes('user');

            if (isEntityKey && obj) {
                 // Try to find a human-readable identifier
                 const name = obj.full_name || obj.name_ar || obj.name || obj.drug_name || obj.drugName || obj.pharmacy_name || obj.pharmacyName || obj.hospital_name || obj.hospitalName || obj.warehouse_name || obj.warehouseName;
                 
                 // Special for prescriptions: try to get patient name if available
                 if (lowerKey.includes('prescription')) {
                     const patientName = obj.patient_name || (obj.Patient && typeof obj.Patient === 'string' ? obj.Patient : null) || (obj.patient && obj.patient.full_name);
                     if (patientName) return `وصفة طبية: ${patientName}`;
                     if (obj.id) return `وصفة طبية رقم #${obj.id}`;
                 }

                  const extra = obj.strength || obj.code || obj.id || '';
                  if (name) return `${name}${extra ? ' (' + formatValue('', extra) + ')' : ''}`;
            }
            
            // Auto-translate ID to Name if it's a drug object but only has ID
            if (lowerKey === 'drug' || lowerKey === 'drug_id') {
                if (drugsMap.value[obj.id || obj]) return drugsMap.value[obj.id || obj];
            }
            
            // 2. Generic Object Handler
            if (obj && typeof obj === 'object') {
                if (Array.isArray(obj)) {
                    // Optimized for list of items (e.g., [ {drug: "Adv", qty: 5}, ... ])
                    return obj.map(v => formatValue('item_in_list', v))
                              .join(', ');
                }
                
                const entries = Object.entries(obj).filter(([k]) => isVisibleField(k));
                
                // Specific Logic for "Item" objects (like in requested items list)
                const nameField = entries.find(([k]) => ['name', 'drug_name', 'drugName', 'drug', 'drug_id'].includes(k));
                const qtyField = entries.find(([k]) => k.toLowerCase().includes('qty') || k.toLowerCase().includes('quantity'));
                
                if (nameField && qtyField && key === 'item_in_list') {
                    const nameVal = nameField[1];
                    const qtyVal = qtyField[1];
                    const translatedName = drugsMap.value[nameVal] || formatValue('', nameVal);
                    return `${translatedName} = ${formatValue('', qtyVal)}`;
                }

                return entries
                    .map(([k, v]) => {
                         const formattedV = formatValue(k, v);
                         return `${formatKey(k)}: ${formattedV}`;
                    })
                    .join(key === 'item_in_list' ? ' | ' : ', ');
            }
        } catch (e) { }
    }

    // Date formatting
    if ((key.toLowerCase().includes('_at') || key.toLowerCase().includes('date') || key === 'dob') && value) {
        try {
            const date = new Date(value);
            if (!isNaN(date)) return date.toLocaleDateString('en-CA').replace(/-/g, '/');
        } catch (e) { }
    }
    
    // Value Translations
    const stringValue = String(value).toLowerCase();
    
    // Check drugsMap for IDs
    if ((key.toLowerCase().includes('drug_id') || key.toLowerCase() === 'drug') && drugsMap.value[value]) {
        return drugsMap.value[value];
    }
    
    if (valueTranslations[stringValue]) return valueTranslations[stringValue];
    
    if (key === 'status') return getStatusText(value);

    // Boolean mapping
    if (typeof value === 'boolean') return value ? 'نعم' : 'لا';
    if (value === 1 || value === '1') {
        if (key.startsWith('is_')) return 'نعم';
    }
    if (value === 0 || value === '0') {
         if (key.startsWith('is_')) return 'لا';
    }
    
    return value;
};

const isVisibleField = (key) => {
    const hidden = [
        'fcm_token', 'remember_token', 'device_token', 
        'email_verified_at', 'two_factor_secret', 
        'two_factor_recovery_codes', 'created_by', 
        'updated_by', 'deleted_by', 'pivot', 
        'id', 'record_id'
    ];
    return !hidden.includes(key);
};

// مراقبة تغيير التبويب
watch(currentTab, () => {
    viewMode.value = 'list';
    fetchReport();
});

onMounted(() => {
    fetchReport();
    fetchDrugs();
});

// ----------------------------------------------------
// 4. دوال مساعدة
// ----------------------------------------------------
const formatDate = (date) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('en-CA').replace(/-/g, '/');
};

const getStatusClass = (status) => {
    // توحيد الحالات
    const s = status ? status.toLowerCase() : '';
    switch (s) {
        case 'active': case 'متوفر': case 'نشط': case 'approved': case 'completed': case 'fulfilled': return 'bg-green-100 text-green-700';
        case 'inactive': case 'معطل': case 'غير نشط': case 'غير متوفر': case 'rejected': case 'cancelled': return 'bg-red-100 text-red-700';
        case 'pending': case 'pending_activation': return 'bg-yellow-100 text-yellow-700';
        default: return 'bg-gray-100 text-gray-700';
    }
};

const getStatusText = (status) => {
    if (!status) return '';
    const s = String(status).toLowerCase();
    const map = {
        'active': 'نشط',
        'inactive': 'غير نشط',
        'pending': 'قيد الانتظار',
        'pending_activation': 'بانتظار التفعيل',
        'approved': 'تمت الموافقة',
        'rejected': 'مرفوض',
        'fulfilled': 'تم التسليم',
        'completed': 'مكتمل',
        'cancelled': 'ملغى'
    };
    return map[s] || status;
};

/* ----------------------------------------------------------------
   Print Selection Logic
---------------------------------------------------------------- */
const isSelectionMode = ref(false);
const selectedIds = ref(new Set());

const toggleSelectionMode = () => {
    isSelectionMode.value = !isSelectionMode.value;
    selectedIds.value = new Set();
};

const toggleSelectAll = () => {
    // Current logical list based on tab
    let list = [];
    if (currentTab.value === 'requests' && viewMode.value === 'details') {
        list = filteredMonthDetails.value;
    } else {
        list = filteredReportData.value;
    }

    if (selectedIds.value.size === list.length) {
        selectedIds.value.clear();
    } else {
        list.forEach(item => selectedIds.value.add(item.id));
    }
};

const toggleRowSelection = (id) => {
    if (selectedIds.value.has(id)) {
        selectedIds.value.delete(id);
    } else {
        selectedIds.value.add(id);
    }
};

const isRowSelected = (id) => selectedIds.value.has(id);

/* ----------------------------------------------------------------
   Filters Logic
---------------------------------------------------------------- */
const searchQuery = ref('');
const dateFrom = ref('');
const dateTo = ref('');
const showDateFilter = ref(false);

const clearFilters = () => {
    searchQuery.value = '';
    dateFrom.value = '';
    dateTo.value = '';
};

const clearDateFilter = () => {
    dateFrom.value = '';
    dateTo.value = '';
};

// Filtered Data Computation
const filteredReportData = computed(() => {
    let data = reportData.value;
    
    // 1. Search Filter
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        data = data.filter(item => {
            // Search logic based on tab
            if (currentTab.value === 'hospitals') {
                return (item.name && item.name.toLowerCase().includes(query)) ||
                       (item.code && item.code.toLowerCase().includes(query));
            } else if (currentTab.value === 'dispensings') {
                return (item.pharmacy && item.pharmacy.includes(query)) ||
                       (item.patient && item.patient.includes(query)) ||
                       (item.drug && item.drug.includes(query));
            } else if (currentTab.value === 'requests') {
                 // Monthly requests list usually just month/year, but lets search inside if needed?
                 // Wait, main view is list of months. Searching month name?
                 return item.monthName && item.monthName.includes(query);
            } else if (currentTab.value === 'activities') {
                 return (item.userName && item.userName.includes(query)) ||
                        (item.action && item.action.includes(query)) ||
                        (item.actionArabic && item.actionArabic.includes(query));
            }
            return false;
        });
    }

    // 2. Date Filter
    if (dateFrom.value || dateTo.value) {
        data = data.filter(item => {
             // Date logic based on tab
             // Most items have 'created_at' or 'date' field
             const itemDate = item.date || item.created_at || item.createdAt || item.month; // month is YYYY-MM
             if (!itemDate) return true;
             
             let itemDateObj;
             if (currentTab.value === 'requests' && viewMode.value === 'list') {
                 // For requests list, item.month is YYYY-MM
                 const [year, month] = item.month.split('-');
                 itemDateObj = new Date(year, month - 1, 1);
             } else {
                 itemDateObj = new Date(itemDate);
             }
             
             if (isNaN(itemDateObj.getTime())) return true;
             
             let matchesFrom = true;
             let matchesTo = true;
             
             if (dateFrom.value) {
                 const fromDate = new Date(dateFrom.value);
                 fromDate.setHours(0, 0, 0, 0);
                 matchesFrom = itemDateObj >= fromDate;
             }
             
             if (dateTo.value) {
                 const toDate = new Date(dateTo.value);
                 toDate.setHours(23, 59, 59, 999);
                 matchesTo = itemDateObj <= toDate;
             }
             
             return matchesFrom && matchesTo;
        });
    }

    return data;
});

// Filtered Month Details (for Requests > Details view)
const filteredMonthDetails = computed(() => {
    let data = monthDetails.value;
    
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        data = data.filter(req => 
            (req.displayId && req.displayId.toLowerCase().includes(query)) ||
            (req.sender && req.sender.includes(query)) ||
            (req.receiver && req.receiver.includes(query))
        );
    }

    if (dateFrom.value || dateTo.value) {
        data = data.filter(req => {
            if (!req.date) return true;
            const reqDate = new Date(req.date);
            if (isNaN(reqDate.getTime())) return true;
            
            let matchesFrom = true;
            let matchesTo = true;
            
            if (dateFrom.value) {
                const fromDate = new Date(dateFrom.value);
                fromDate.setHours(0, 0, 0, 0);
                matchesFrom = reqDate >= fromDate;
            }
            
            if (dateTo.value) {
                const toDate = new Date(dateTo.value);
                toDate.setHours(23, 59, 59, 999);
                matchesTo = reqDate <= toDate;
            }
            
            return matchesFrom && matchesTo;
        });
    }

    return data;
});

const displayedData = computed(() => {
    if (currentTab.value === 'requests' && viewMode.value === 'details') {
        return []; // We use specific loop for details
    }
    return filteredReportData.value;
});

// إحصائيات عمليات الصرف
const totalDispensedQuantity = computed(() => {
    if (currentTab.value !== 'dispensings') return 0;
    return filteredReportData.value.reduce((total, dispensing) => {
        return total + (parseInt(dispensing.quantity) || 0);
    }, 0);
});

const uniqueDrugsCount = computed(() => {
    if (currentTab.value !== 'dispensings') return 0;
    const drugs = new Set(filteredReportData.value.map(d => d.drug));
    return drugs.size;
});

const uniquePatientsCount = computed(() => {
    if (currentTab.value !== 'dispensings') return 0;
    const patients = new Set(filteredReportData.value.map(d => d.patient));
    return patients.size;
});

</script>

<template>
<DefaultLayout>
    <main class="flex-1 p-4 sm:p-8 pt-20 sm:pt-5 min-h-screen bg-[#F8FAFC]">
        
        <!-- Header -->
        <div class="mb-8 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h1 class="text-3xl font-bold text-[#4DA1A9] mb-2 flex items-center gap-3">
                    <Icon icon="carbon:report-data" class="w-10 h-10 text-[#4DA1A9]" />
                    التقارير الشاملة
                </h1>
                <p class="text-gray-500">استعراض وتحليل بيانات النظام والتقارير التفصيلية</p>
            </div>
            
            <!-- أزرار الإجراءات -->
            <div class="flex items-center gap-3 no-print">
                <div v-if="isPrinting" class="text-sm font-bold text-[#4DA1A9] animate-pulse">
                    {{ printProgress }}
                </div>
                
                <!-- Selection Mode Toolbar -->
                <div v-if="isSelectionMode" class="flex items-center gap-2 bg-white ml-2">
                     <button 
                        @click="toggleSelectAll"
                        class="px-4 py-2 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 transition-colors font-bold text-sm"
                    >
                        {{ selectedIds.size > 0 && selectedIds.size === (currentTab === 'requests' && viewMode === 'details' ? filteredMonthDetails.length : filteredReportData.length) ? 'إلغاء تحديد الكل' : 'تحديد الكل' }}
                    </button>
                     <button 
                        @click="printFullReport"
                        class="px-4 py-2 bg-[#4DA1A9] text-white rounded-xl hover:bg-[#3d8b92] transition-colors font-bold text-sm flex items-center gap-2"
                    >
                        <Icon icon="solar:printer-bold" class="w-4 h-4" />
                        طباعة المحدد ({{ selectedIds.size }})
                    </button>
                    <button 
                        @click="toggleSelectionMode"
                        class="px-4 py-2 bg-red-50 text-red-600 rounded-xl hover:bg-red-100 transition-colors font-bold text-sm"
                    >
                        إلغاء
                    </button>
                </div>

                <!-- Filters & Search -->
                <div class="flex items-center gap-2 no-print bg-white p-1 rounded-xl border border-gray-100 shadow-sm" v-if="!isSelectionMode">
                     <div class="relative">
                        <Icon icon="solar:magnifer-linear" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 w-4 h-4" />
                        <input 
                            type="text" 
                            v-model="searchQuery" 
                            placeholder="بحث..." 
                            class="pl-3 pr-9 py-1.5 text-sm w-40 rounded-lg border-none bg-gray-50 focus:ring-1 focus:ring-[#4DA1A9]/20 focus:bg-white transition-all text-[#4DA1A9]"
                        >
                     </div>
                     <div class="h-6 w-px bg-gray-200 mx-1"></div>
                     <div class="flex items-center gap-1">
                        <!-- زر إظهار/إخفاء فلتر التاريخ -->
                        <button
                            @click="showDateFilter = !showDateFilter"
                            class="h-9 w-9 flex items-center justify-center border border-gray-300 rounded-4xl bg-gray-50 text-gray-600 hover:bg-gray-100 hover:border-[#4DA1A9] transition-all duration-200"
                            :title="showDateFilter ? 'إخفاء فلتر التاريخ' : 'إظهار فلتر التاريخ'"
                        >
                            <Icon
                                icon="solar:calendar-bold"
                                class="w-4 h-4"
                            />
                        </button>

                        <!-- فلتر التاريخ -->
                        <Transition
                            enter-active-class="transition duration-200 ease-out"
                            enter-from-class="opacity-0 scale-95"
                            enter-to-class="opacity-100 scale-100"
                            leave-active-class="transition duration-150 ease-in"
                            leave-from-class="opacity-0 scale-95"
                            leave-to-class="opacity-100 scale-100"
                        >
                            <div v-if="showDateFilter" class="flex items-center gap-2">
                                <span class="text-xs font-bold text-[#4DA1A9] whitespace-nowrap bg-[#4DA1A9]/5 px-3 py-1.5 rounded-lg border border-[#4DA1A9]/20">
                                    فلترة بأيام محددة:
                                </span>
                                <div class="relative">
                                    <input
                                        type="date"
                                        v-model="dateFrom"
                                        class="py-1.5 px-3 pr-8 text-sm rounded-lg border border-gray-300 bg-white text-gray-700 focus:outline-none focus:border-[#4DA1A9] cursor-pointer"
                                        placeholder="من تاريخ"
                                    />
                                    <Icon
                                        icon="solar:calendar-linear"
                                        class="w-4 h-4 text-[#4DA1A9] absolute right-2 top-1/2 -translate-y-1/2 pointer-events-none"
                                    />
                                </div>
                                <span class="text-gray-600 font-medium text-sm">إلى</span>
                                <div class="relative">
                                    <input
                                        type="date"
                                        v-model="dateTo"
                                        class="py-1.5 px-3 pr-8 text-sm rounded-lg border border-gray-300 bg-white text-gray-700 focus:outline-none focus:border-[#4DA1A9] cursor-pointer"
                                        placeholder="إلى تاريخ"
                                    />
                                    <Icon
                                        icon="solar:calendar-linear"
                                        class="w-4 h-4 text-[#4DA1A9] absolute right-2 top-1/2 -translate-y-1/2 pointer-events-none"
                                    />
                                </div>
                                <button 
                                    v-if="dateFrom || dateTo"
                                    @click="clearDateFilter"
                                    class="p-1 text-red-500 hover:bg-red-50 rounded transition-colors"
                                    title="مسح فلتر التاريخ"
                                >
                                    <Icon icon="solar:trash-bin-trash-bold" class="w-5 h-5" />
                                </button>
                            </div>
                        </Transition>

                      
                     </div>
                </div>

                <!-- Default Printer Button -->
                <button 
                    v-if="!isSelectionMode"
                    @click="toggleSelectionMode" 
                    class="px-4 py-2 bg-[#4DA1A9] text-white border border-[#4DA1A9] rounded-xl hover:bg-[#3d8b92] transition-colors flex items-center gap-2 shadow-sm font-medium"
                    :disabled="isPrinting || isLoading"
                >
                    <Icon icon="solar:printer-bold" class="w-5 h-5" :class="{'animate-pulse': isPrinting}" />
                    {{ isPrinting ? 'جاري التحضير...' : 'طباعة الصفوف' }}
                </button>

              
            </div>
        </div>

        <!-- Tabs Navigation -->
        <div class="bg-white rounded-2xl p-2 shadow-sm border border-gray-100 mb-8 overflow-x-auto no-print">
            <div class="flex space-x-2 space-x-reverse min-w-max">
                <button
                    v-for="tab in tabs"
                    :key="tab.id"
                    @click="currentTab = tab.id"
                    class="flex items-center gap-2 px-6 py-3 rounded-xl transition-all duration-200 font-bold whitespace-nowrap"
                    :class="[
                        currentTab === tab.id 
                            ? 'bg-[#4DA1A9] text-white shadow-lg shadow-[#4DA1A9]/20 transform -translate-y-0.5' 
                            : 'text-gray-500 hover:bg-gray-50 hover:text-[#4DA1A9]'
                    ]"
                >
                    <Icon :icon="tab.icon" class="w-5 h-5" />
                    {{ tab.name }}
                </button>
            </div>
        </div>

        <!-- Content Area -->
        <div class="bg-white rounded-3xl shadow-sm border border-gray-100 min-h-[400px] overflow-hidden relative">
            
            <!-- Loading -->
            <div v-if="isLoading" class="absolute inset-0 flex items-center justify-center bg-white/80 backdrop-blur-sm z-10">
                <LoadingState message="جاري إعداد التقرير..." />
            </div>

            <!-- Error -->
            <div v-else-if="error" class="p-12">
                <ErrorState :message="error" :retry="fetchReport" />
            </div>

            <!-- Data Tables -->
            <div v-else class="p-6">
                
                <!-- 1. Hospitals Report -->
                <div v-if="currentTab === 'hospitals'" class="overflow-x-auto">
                     <EmptyState v-if="reportData.length === 0" title="لا توجد بيانات" message="لم يتم العثور على مؤسسات مسجلة" />
                     <table v-else class="w-full text-right border-collapse">
                        <thead>
                            <tr v-if="isPrinting" class="hidden print:table-row">
                                <th colspan="9" class="p-0 border-none">
                                    <div class="text-center mb-6 pb-4 border-b-2 border-[#4DA1A9]">
                                        <h1 class="text-[#4DA1A9] text-2xl font-bold mb-2">تقرير المؤسسات الصحية</h1>
                                        <div class="flex justify-center gap-6 text-sm text-gray-600">
                                            <span>تاريخ الطباعة: {{ new Date().toLocaleString('ar-SA') }}</span>
                                            <span>عدد المسجلين: {{ reportData.length }}</span>
                                        </div>
                                    </div>
                                </th>
                            </tr>
                            <tr class="bg-gray-50 text-[#4DA1A9]">
                                <th v-if="isSelectionMode" class="p-4 w-10 text-center no-print">
                                    <input type="checkbox" :checked="selectedIds.size > 0 && selectedIds.size === filteredReportData.length" @change="toggleSelectAll" class="rounded border-gray-300 text-[#4DA1A9] focus:ring-[#4DA1A9]">
                                </th>
                                <th class="p-4 rounded-r-xl">المؤسسة</th>
                                <th class="p-4">عدد الأقسام</th>
                                <th class="p-4">عدد الصيدليات</th>
                                <th class="p-4">عدد المرضى</th>
                                <th class="p-4">عدد الموظفين</th>
                                <th class="p-4">المخازن</th>
                                <th class="p-4">الوصفات النشطة</th>
                                <th class="p-4 rounded-l-xl">الحالة</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <template v-for="hospital in filteredReportData" :key="hospital.id">
                            <tr class="hover:bg-[#F8FAFC] transition-colors group" :class="{ 'no-print': isSelectionMode && !isRowSelected(hospital.id) }">
                                <td v-if="isSelectionMode" class="p-4 text-center no-print">
                                    <input type="checkbox" :checked="isRowSelected(hospital.id)" @change="toggleRowSelection(hospital.id)" class="rounded border-gray-300 text-[#4DA1A9] focus:ring-[#4DA1A9]">
                                </td>
                                <td class="p-4 font-bold text-[#4DA1A9] group-hover:text-[#4DA1A9] transition-colors">
                                    {{ hospital.name }}
                                    <div class="text-xs text-gray-400 font-normal mt-1">{{ hospital.code }}</div>
                                </td>
                                <td class="p-4 text-gray-600">
                                    <button @click="openDepartmentsModal(hospital)" class="px-3 py-1 bg-blue-50 hover:bg-blue-100 text-blue-700 rounded-lg font-bold transition-colors min-w-[2.5rem]">
                                        {{ hospital.statistics?.departmentsCount || 0 }}
                                    </button>
                                </td>
                                <td class="p-4 text-gray-600">
                                    <button @click="openPharmaciesModal(hospital)" class="px-3 py-1 bg-green-50 hover:bg-green-100 text-green-700 rounded-lg font-bold transition-colors min-w-[2.5rem]">
                                        {{ hospital.statistics?.pharmaciesCount || 0 }}
                                    </button>
                                </td>
                                <td class="p-4 font-bold">{{ hospital.statistics?.patientsCount || 0 }}</td>
                                <td class="p-4 font-bold">{{ hospital.statistics?.staffCount || 0 }}</td>
                                <td class="p-4 font-bold">{{ hospital.statistics?.warehousesCount || 0 }}</td>
                                <td class="p-4 font-bold text-[#4DA1A9]">{{ hospital.statistics?.activePrescriptions || 0 }}</td>
                                <td class="p-4">
                                    <span :class="['px-3 py-1 rounded-lg text-xs font-bold', getStatusClass(hospital.status || hospital.statusArabic)]">
                                        {{ getStatusText(hospital.status) }}
                                    </span>
                                </td>
                            </tr>
                            <!-- بيانات الطباعة الإضافية: الأقسام -->
                            <tr v-if="isPrinting && hospital.fullDepartments && hospital.fullDepartments.length > 0" class="print-row bg-blue-50/20">
                                <td colspan="9" class="p-4 border-t border-blue-100">
                                    <div class="text-sm font-bold text-blue-800 mb-2">الأقسام التابعة لـ {{ hospital.name }}:</div>
                                    <table class="w-full text-right text-xs border border-blue-200 bg-white">
                                        <thead class="bg-blue-50">
                                            <tr>
                                                <th class="p-2 border border-blue-100">القسم</th>
                                                <th class="p-2 border border-blue-100">رئيس القسم</th>
                                                <th class="p-2 border border-blue-100">الحالة</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="dept in hospital.fullDepartments" :key="dept.id">
                                                <td class="p-2 border border-blue-100">{{ dept.name }}</td>
                                                <td class="p-2 border border-blue-100">{{ dept.head }}</td>
                                                <td class="p-2 border border-blue-100">{{ getStatusText(dept.status) }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                            <!-- بيانات الطباعة الإضافية: الصيدليات -->
                            <tr v-if="isPrinting && hospital.fullPharmacies && hospital.fullPharmacies.length > 0" class="print-row bg-green-50/20">
                                <td colspan="9" class="p-4 border-t border-green-100">
                                    <div class="text-sm font-bold text-green-800 mb-2">الصيدليات التابعة لـ {{ hospital.name }}:</div>
                                    <div v-for="pharm in hospital.fullPharmacies" :key="pharm.id" class="mb-4 last:mb-0 border border-green-200 rounded p-2 bg-white">
                                        <div class="font-bold text-green-700 mb-1 border-b pb-1">{{ pharm.name }}</div>
                                        <table v-if="pharm.inventory && pharm.inventory.length > 0" class="w-full text-right text-xs">
                                            <thead class="bg-green-50">
                                                <tr>
                                                    <th class="p-1">الدواء</th>
                                                    <th class="p-1">الكمية</th>
                                                    <th class="p-1">الصلاحية</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-for="(inv, idx) in pharm.inventory" :key="idx" class="border-t border-green-50">
                                                    <td class="p-1">{{ inv.drug_name }}</td>
                                                    <td class="p-1 font-mono">{{ inv.quantity }}</td>
                                                    <td class="p-1 font-mono">{{ inv.expiry_date }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                        <div v-else class="text-xs text-gray-500 italic p-1">المخزون فارغ</div>
                                    </div>
                                </td>
                            </tr>
                            </template>
                        </tbody>
                    </table>
                </div>


                <!-- 3. Dispensings Report -->
                <div v-if="currentTab === 'dispensings'" class="overflow-x-auto">
                    <EmptyState v-if="reportData.length === 0" title="لا توجد عمليات صرف" message="سجل الصرف فارغ" />
                    
                    <!-- طباعة محسنة - تظهر فقط أثناء الطباعة -->
                    <div v-if="isPrinting && reportData.length > 0" class="print-only-dispensings">
                        <div class="print-header">
                            <h1 class="print-title">تقرير عمليات الصرف الدوائي</h1>
                            <div class="print-meta">
                                <span>تاريخ التقرير: {{ new Date().toLocaleDateString('ar-SA') }}</span>
                                <span>عدد العمليات: {{ filteredReportData.length }}</span>
                                <span v-if="dateFrom || dateTo">الفترة: {{ dateFrom || 'غير محدد' }} - {{ dateTo || 'غير محدد' }}</span>
                            </div>
                        </div>

                        <!-- ملخص الإحصائيات -->
                        <div class="print-summary">
                            <h2>ملخص الإحصائيات</h2>
                            <div class="summary-grid">
                                <div class="summary-item">
                                    <div class="summary-value">{{ filteredReportData.length }}</div>
                                    <div class="summary-label">إجمالي العمليات</div>
                                </div>
                                <div class="summary-item">
                                    <div class="summary-value">{{ totalDispensedQuantity }}</div>
                                    <div class="summary-label">إجمالي الكميات المصروفة</div>
                                </div>
                                <div class="summary-item">
                                    <div class="summary-value">{{ uniqueDrugsCount }}</div>
                                    <div class="summary-label">أنواع الأدوية المختلفة</div>
                                </div>
                                <div class="summary-item">
                                    <div class="summary-value">{{ uniquePatientsCount }}</div>
                                    <div class="summary-label">عدد المرضى</div>
                                </div>
                            </div>
                        </div>

                        <!-- تفاصيل العمليات -->
                        <div class="operations-details">
                            <h2>تفاصيل عمليات الصرف</h2>
                            
                            <div v-for="(dispensing, index) in filteredReportData" :key="dispensing.id" 
                                 v-show="!isSelectionMode || isRowSelected(dispensing.id)"
                                 class="operation-card">
                                
                                <div class="operation-header">
                                    <div class="operation-number">عملية رقم {{ index + 1 }}</div>
                                    <div class="operation-status">مكتملة</div>
                                </div>
                                
                                <div class="operation-info">
                                    <div class="info-row">
                                        <span class="info-label">الصيدلية:</span>
                                        <span class="info-value">{{ dispensing.pharmacy }}</span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">المريض:</span>
                                        <span class="info-value">{{ dispensing.patient }}</span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">الصيدلي:</span>
                                        <span class="info-value">{{ dispensing.pharmacist }}</span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">تاريخ العملية:</span>
                                        <span class="info-value">{{ dispensing.date }}</span>
                                    </div>
                                </div>

                                <!-- جدول الأدوية المصروفة -->
                                <div v-if="dispensing.fullDetails && dispensing.fullDetails.length > 0" class="medications-table">
                                    <h3>الأدوية المصروفة</h3>
                                    <table class="medications-table-content">
                                        <thead>
                                            <tr>
                                                <th>اسم الدواء</th>
                                                <th>الكمية المصروفة</th>
                                                <th>تاريخ الصلاحية</th>
                                                <th>رقم الدفعة</th>
                                                <th>ملاحظات</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="(detail, idx) in dispensing.fullDetails" :key="idx">
                                                <td class="drug-name">{{ detail.drug_name || detail.drug || 'غير محدد' }}</td>
                                                <td class="quantity">{{ detail.quantity || detail.dispensed_quantity || 0 }}</td>
                                                <td class="expiry">{{ detail.expiry_date || detail.expiration_date || '-' }}</td>
                                                <td class="batch">{{ detail.batch_number || detail.lot_number || '-' }}</td>
                                                <td class="notes">{{ detail.notes || detail.remarks || '-' }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- إذا لم تكن هناك تفاصيل -->
                                <div v-else class="simple-medication">
                                    <div class="medication-item">
                                        <span class="drug-name">{{ dispensing.drug }}</span>
                                        <span class="quantity">الكمية: {{ dispensing.quantity }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- تذييل التقرير -->
                        <div class="print-footer">
                            <div class="footer-line"></div>
                            <p>تم إنشاء هذا التقرير بواسطة نظام إدارة المستشفيات - {{ new Date().toLocaleString('ar-SA') }}</p>
                        </div>
                    </div>

                    <!-- الجدول العادي - يظهر في الشاشة فقط -->
                    <table v-if="!isPrinting && reportData.length > 0" class="w-full text-right border-collapse">
                            <thead>
                                <tr class="bg-gray-50 text-[#4DA1A9]">
                                    <th v-if="isSelectionMode" class="p-4 w-10 text-center no-print">
                                        <input type="checkbox" :checked="selectedIds.size > 0 && selectedIds.size === filteredReportData.length" @change="toggleSelectAll" class="rounded border-gray-300 text-[#4DA1A9] focus:ring-[#4DA1A9]">
                                    </th>
                                    <th class="p-4 rounded-r-xl">الصيدلية</th>
                                    <th class="p-4">المريض</th>
                                    <th class="p-4">الدواء</th>
                                    <th class="p-4">الكمية</th>
                                    <th class="p-4">الصيدلي</th>
                                    <th class="p-4">التاريخ</th>
                                    <th class="p-4 rounded-l-xl">الحالة</th>
                                </tr>
                            </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="dispensing in filteredReportData" :key="dispensing.id" class="hover:bg-[#F8FAFC] transition-colors" :class="{ 'no-print': isSelectionMode && !isRowSelected(dispensing.id) }">
                                <td v-if="isSelectionMode" class="p-4 text-center no-print">
                                    <input type="checkbox" :checked="isRowSelected(dispensing.id)" @change="toggleRowSelection(dispensing.id)" class="rounded border-gray-300 text-[#4DA1A9] focus:ring-[#4DA1A9]">
                                </td>
                                <td class="p-4 font-bold text-[#4DA1A9]">{{ dispensing.pharmacy }}</td>
                                <td class="p-4 text-gray-600 font-medium">{{ dispensing.patient }}</td>
                                <td class="p-4 text-[#4DA1A9] font-bold">{{ dispensing.drug }}</td>
                                <td class="p-4 font-mono font-bold">{{ dispensing.quantity }}</td>
                                <td class="p-4 text-gray-500 text-sm">{{ dispensing.pharmacist }}</td>
                                <td class="p-4 text-gray-500 font-mono text-sm" dir="ltr">{{ dispensing.date }}</td>
                                <td class="p-4">
                                    <span class="px-3 py-1 rounded-lg text-xs font-bold bg-green-50 text-green-700 border border-green-200">
                                        مكتملة
                                    </span>
                                </td>
                            </tr>
                            <!-- بيانات الطباعة الإضافية: تفاصيل العملية -->
                             <tr v-if="isPrinting && (!isSelectionMode || isRowSelected(dispensing.id)) && dispensing.fullDetails && dispensing.fullDetails.length > 0" class="print-row bg-gray-50/50">
                                 <td colspan="7" class="p-4 border-t border-gray-200">
                                     <div class="text-sm font-bold text-gray-700 mb-2">تفاصيل عملية الصرف للمريض {{ dispensing.patient }}:</div>
                                     <table class="w-full text-right text-xs border border-gray-200 bg-white">
                                         <thead class="bg-gray-100">
                                             <tr>
                                                 <th class="p-2 border border-gray-200">الدواء</th>
                                                 <th class="p-2 border border-gray-200">الكمية المصروفة</th>
                                                 <th class="p-2 border border-gray-200">تاريخ الصلاحية</th>
                                                 <th class="p-2 border border-gray-200">رقم الدفعة</th>
                                                 <th class="p-2 border border-gray-200">ملاحظات</th>
                                             </tr>
                                         </thead>
                                         <tbody>
                                             <tr v-for="(detail, idx) in dispensing.fullDetails" :key="idx">
                                                 <td class="p-2 border border-gray-200 font-medium">
                                                     {{ detail.drug_name || detail.drug || 'غير محدد' }}
                                                 </td>
                                                 <td class="p-2 border border-gray-200 font-mono font-bold text-[#4DA1A9]">
                                                     {{ detail.quantity || detail.dispensed_quantity || 0 }}
                                                 </td>
                                                 <td class="p-2 border border-gray-200 font-mono text-gray-600" dir="ltr">
                                                     {{ detail.expiry_date || detail.expiration_date || '-' }}
                                                 </td>
                                                 <td class="p-2 border border-gray-200 font-mono text-gray-500">
                                                     {{ detail.batch_number || detail.lot_number || '-' }}
                                                 </td>
                                                 <td class="p-2 border border-gray-200 text-gray-600">
                                                     {{ detail.notes || detail.remarks || '-' }}
                                                 </td>
                                             </tr>
                                         </tbody>
                                     </table>
                                 </td>
                             </tr>
                        </tbody>
                    </table>
                </div>

                <!-- 4. Monthly Requests Report -->
                <div v-if="currentTab === 'requests'">
                     <!-- List View -->
                     <div v-if="viewMode === 'list'">
                        <EmptyState v-if="reportData.length === 0" title="لا توجد بيانات" message="لا توجد بيانات للطلبات الشهرية" />
                        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div 
                                v-for="(item, index) in filteredReportData" 
                                :key="index" 
                                @click="showMonthDetails(item)"
                                class="bg-white border border-gray-200 rounded-2xl p-6 hover:shadow-lg transition-all cursor-pointer transform hover:-translate-y-1"
                            >
                                <div class="flex items-center justify-between mb-4">
                                    <h3 class="text-lg font-bold text-[#4DA1A9]">{{ item.monthName }}</h3>
                                    <div class="w-10 h-10 bg-[#4DA1A9]/10 rounded-full flex items-center justify-center">
                                        <Icon icon="solar:calendar-bold" class="w-6 h-6 text-[#4DA1A9]" />
                                    </div>
                                </div>
                                <div class="border-b border-gray-100 pb-2 mb-2 text-xs text-gray-400 font-mono">{{ item.month }}</div>
                                <div class="space-y-3">
                                    <div class="flex justify-between items-center text-sm">
                                        <span class="text-gray-600">الطلبات الداخلية</span>
                                        <span class="font-bold text-[#4DA1A9]">{{ item.internalRequests }}</span>
                                    </div>
                                    <div class="flex justify-between items-center text-sm">
                                        <span class="text-gray-600">الطلبات الخارجية</span>
                                        <span class="font-bold text-blue-600">{{ item.externalRequests }}</span>
                                    </div>
                                    <div class="flex justify-between items-center text-sm">
                                        <span class="text-gray-600">طلبات التحويل</span>
                                        <span class="font-bold text-orange-600">{{ item.transferRequests }}</span>
                                    </div>
                                    <div class="pt-3 mt-3 border-t border-gray-100 flex justify-between items-center">
                                        <span class="font-bold text-[#4DA1A9]">الإجمالي</span>
                                        <div class="bg-[#4DA1A9] text-white px-3 py-1 rounded-full text-sm font-bold shadow-sm">
                                            {{ item.total }}
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-4 text-center text-sm text-[#4DA1A9] flex items-center justify-center gap-2 font-bold bg-cyan-50/50 border border-cyan-100 py-2.5 rounded-xl group-hover:bg-[#4DA1A9] group-hover:text-white transition-colors">
                                    <Icon icon="solar:eye-broken" class="w-4 h-4" />
                                    عرض التفاصيل
                                </div>
                            </div>
                        </div>
                     </div>

                     <!-- Details View -->
                     <div v-else>
                        <div class="mb-6 flex items-center gap-4">
                            <button @click="backToMonths" class="p-2.5 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 hover:border-[#4DA1A9]/50 text-[#4DA1A9] transition-all shadow-sm flex items-center justify-center group">
                                <Icon icon="solar:arrow-right-bold" class="w-6 h-6 group-hover:scale-110 transition-transform" />
                            </button>
                            <div>
                                <h2 class="text-xl font-bold text-[#4DA1A9] flex items-center gap-2">
                                    تفاصيل الطلبات
                                    <span v-if="selectedMonthData" class="text-[#4DA1A9] text-base font-normal">
                                        ({{ selectedMonthData.monthName }})
                                    </span>
                                </h2>
                            </div>
                        </div>

                        <div v-if="isDetailLoading" class="min-h-[300px] flex items-center justify-center">
                            <LoadingState message="جاري تحميل التفاصيل..." />
                        </div>
                        <div v-else-if="filteredMonthDetails.length === 0" class="min-h-[300px] flex flex-col items-center justify-center">
                            <EmptyState title="لا توجد طلبات" message="لم يتم تسجيل أي طلبات في هذا الشهر" />
                        </div>
                        <div v-else class="overflow-x-auto">
                            <table class="w-full text-right border-collapse">
                                <thead>
                                    <tr class="bg-gray-50 text-[#4DA1A9]">
                                        <th v-if="isSelectionMode" class="p-4 w-10 text-center no-print">
                                            <input type="checkbox" :checked="selectedIds.size > 0 && selectedIds.size === filteredMonthDetails.length" @change="toggleSelectAll" class="rounded border-gray-300 text-[#4DA1A9] focus:ring-[#4DA1A9]">
                                        </th>
                                        <th class="p-4 rounded-r-xl">رقم الطلب</th>
                                        <th class="p-4">نوع الطلب</th>
                                        <th class="p-4">مرسل الطلب</th>
                                        <th class="p-4">مستقبل الطلب</th>
                                        <th class="p-4">التاريخ</th>
                                        <th class="p-4">عدد المواد</th>
                                        <th class="p-4 rounded-l-xl">الحالة</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <template v-for="req in filteredMonthDetails" :key="req.id">
                                    <tr class="hover:bg-[#F8FAFC] transition-colors" :class="{ 'no-print': isSelectionMode && !isRowSelected(req.id) }">
                                        <td v-if="isSelectionMode" class="p-4 text-center no-print">
                                            <input type="checkbox" :checked="isRowSelected(req.id)" @change="toggleRowSelection(req.id)" class="rounded border-gray-300 text-[#4DA1A9] focus:ring-[#4DA1A9]">
                                        </td>
                                        <td class="p-4 font-mono text-sm font-bold text-[#4DA1A9]">{{ req.displayId }}</td>
                                        <td class="p-4">
                                            <span class="px-2 py-1 bg-gray-100 rounded text-gray-700 text-xs font-bold"
                                                :class="{
                                                    'bg-blue-50 text-blue-700': req.type === 'external',
                                                    'bg-green-50 text-green-700': req.type === 'internal',
                                                    'bg-orange-50 text-orange-700': req.type === 'transfer'
                                                }"
                                            >
                                                {{ req.typeArabic }}
                                            </span>
                                        </td>
                                        <td class="p-4 text-gray-600 font-medium">{{ req.sender }}</td>
                                        <td class="p-4 text-gray-600 font-medium">{{ req.receiver }}</td>
                                        <td class="p-4 text-gray-500 font-mono text-sm" dir="ltr">{{ req.date }}</td>
                                        <td class="p-4 font-bold text-center">
                                            <button 
                                                @click.stop="openItemsModal(req)"
                                                class="px-4 py-1.5 bg-white border border-gray-200 hover:bg-[#4DA1A9] hover:text-white hover:border-[#4DA1A9] rounded-xl transition-all text-[#4DA1A9] font-bold text-sm inline-flex items-center gap-2 shadow-sm"
                                            >
                                                {{ req.itemsCount }}
                                                <Icon icon="solar:eye-broken" class="w-4 h-4" />
                                            </button>
                                        </td>
                                        <td class="p-4">
                                            <span :class="['px-3 py-1 rounded-lg text-xs font-bold', getStatusClass(req.status)]">
                                                {{ getStatusText(req.status) }}
                                            </span>
                                        </td>
                                    </tr>
                            <!-- بيانات الطباعة الإضافية: عناصر الطلب -->
                             <tr v-if="isPrinting && req.fullItems && req.fullItems.length > 0" class="print-row bg-gray-50/50">
                                <td colspan="7" class="p-4 border-t border-gray-200">
                                     <div class="text-sm font-bold text-gray-700 mb-2">تفاصيل المواد للطلب {{ req.displayId }}:</div>
                                     <table class="w-full text-right text-xs border border-gray-200 bg-white">
                                         <thead class="bg-gray-100">
                                             <tr>
                                                 <th class="p-2 border border-gray-200">الاسم / الدواء</th>
                                                 <th class="p-2 border border-gray-200">الكمية المطلوبة</th>
                                                 <th class="p-2 border border-gray-200">الكمية الموافقة</th>
                                             </tr>
                                         </thead>
                                         <tbody>
                                             <tr v-for="(item, idx) in req.fullItems" :key="idx">
                                                 <td class="p-2 border border-gray-200">
                                                     {{ item.name }}
                                                     <div v-if="item.details" class="text-[10px] text-gray-500">{{ item.details }}</div>
                                                 </td>
                                                 <td class="p-2 border border-gray-200 font-mono">{{ item.qty }}</td>
                                                 <td class="p-2 border border-gray-200 font-mono">{{ item.approved_qty || '-' }}</td>
                                             </tr>
                                         </tbody>
                                     </table>
                                </td>
                             </tr>
                            </template>
                                </tbody>
                            </table>
                        </div>
                     </div>
                </div>

                <!-- 5. Activities Report -->
                <div v-if="currentTab === 'activities'" class="overflow-x-auto">
                    <EmptyState v-if="reportData.length === 0" title="لا توجد بيانات" message="سجل النشاطات فارغ" />
                    <table v-else class="w-full text-right border-collapse">
                        <thead>
                            <tr class="bg-gray-50 text-[#2C5282]">
                                <th v-if="isSelectionMode" class="p-4 w-10 text-center no-print">
                                    <input type="checkbox" :checked="selectedIds.size > 0 && selectedIds.size === filteredReportData.length" @change="toggleSelectAll" class="rounded border-gray-300 text-[#2C5282] focus:ring-[#2C5282]">
                                </th>
                                <th class="p-4 rounded-r-xl">المستخدم</th>
                                <th class="p-4">نوع المستخدم</th>
                                <th class="p-4">يتبع</th>
                                <th class="p-4">النشاط</th>
                                <th class="p-4">تفاصيل النشاط</th>
                                <th class="p-4 rounded-l-xl">التوقيت</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                             <template v-for="log in filteredReportData" :key="log.id">
                            <tr class="hover:bg-[#F8FAFC] transition-colors" :class="{ 'no-print': isSelectionMode && !isRowSelected(log.id) }">
                                <td v-if="isSelectionMode" class="p-4 text-center no-print">
                                    <input type="checkbox" :checked="isRowSelected(log.id)" @change="toggleRowSelection(log.id)" class="rounded border-gray-300 text-[#2C5282] focus:ring-[#2C5282]">
                                </td>
                                <td class="p-4 font-bold text-[#2C5282]">{{ log.userName || 'غير معروف' }}</td>
                                <td class="p-4 text-gray-600 text-sm">{{ log.userTypeArabic || log.userType }}</td>
                                <td class="p-4">
                                     <span class="px-2 py-1  text-[#2C5282]">
                                         {{ log.affiliation || '-' }}
                                     </span>
                                </td>
                                <td class="p-4">
                                    <span class="font-medium inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-gray-100 text-gray-700">
                                        {{ log.actionArabic || log.action }}
                                    </span>
                                </td>
                                <td class="p-4">
                                    <button 
                                        @click="openActivityModal(log)"
                                        class="flex items-center gap-2 text-[#2C5282] hover:text-white hover:bg-[#2C5282] transition-all font-bold text-sm bg-white border border-blue-100/50 hover:border-[#2C5282] px-4 py-1.5 rounded-xl shadow-sm group"
                                    >
                                        <Icon icon="solar:document-text-bold-duotone" class="w-4 h-4" />
                                        <span>عرض التفاصيل</span>
                                        <!-- <span class="text-xs text-gray-400 font-normal font-mono group-hover:text-white/80">({{ formatTableName(log.tableName) }} #{{ log.recordId }})</span> -->
                                    </button>
                                </td>
                                <td class="p-4 text-gray-500 font-mono text-sm" dir="ltr">{{ log.createdAt }}</td>
                            </tr>
                            <!-- بيانات الطباعة الإضافية: النشاط -->
                             <tr v-if="isPrinting && (!isSelectionMode || isRowSelected(log.id)) && (log.details?.old || log.details?.new)" class="print-row bg-gray-50/50">
                                 <td colspan="6" class="p-4 border-t border-gray-100">
                                     <div class="grid grid-cols-2 gap-4 text-xs">
                                         <!-- القيم القديمة -->
                                         <div v-if="log.details.old" class="border border-red-200 rounded bg-white">
                                              <div class="bg-red-50 text-red-700 font-bold p-1 border-b border-red-100">بيانات سابقة</div>
                                              <div class="p-2 grid grid-cols-2 gap-1">
                                                  <template v-for="(val, key) in log.details.old" :key="key">
                                                      <div v-if="isVisibleField(key)" class="col-span-2 flex justify-between border-b border-gray-50 last:border-0">
                                                          <span class="text-gray-500">{{ formatKey(key) }}:</span>
                                                          <span class="font-medium">{{ formatValue(key, val) }}</span>
                                                      </div>
                                                  </template>
                                              </div>
                                         </div>
                                         <div v-else></div>

                                         <!-- القيم الجديدة -->
                                         <div v-if="log.details.new" class="border border-green-200 rounded bg-white">
                                              <div class="bg-green-50 text-green-700 font-bold p-1 border-b border-green-100">بيانات جديدة</div>
                                              <div class="p-2 grid grid-cols-2 gap-1">
                                                  <template v-for="(val, key) in log.details.new" :key="key">
                                                      <div v-if="isVisibleField(key)" class="col-span-2 flex justify-between border-b border-gray-50 last:border-0">
                                                          <span class="text-gray-500">{{ formatKey(key) }}:</span>
                                                          <span class="font-bold text-green-700">{{ formatValue(key, val) }}</span>
                                                      </div>
                                                  </template>
                                              </div>
                                         </div>
                                     </div>
                                 </td>
                             </tr>
                             </template>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>

        <!-- Items Modal -->
        <div v-if="isItemsModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div @click="closeItemsModal" class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
            <div class="relative bg-[#F2F2F2] rounded-3xl shadow-2xl w-full max-w-2xl max-h-[80vh] overflow-y-auto transform transition-all scale-100" dir="rtl">
                <div class="bg-[#2C5282] px-8 py-5 flex justify-between items-center relative overflow-hidden sticky top-0 z-20">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-16 -mt-16 blur-2xl"></div>
                    <div class="absolute bottom-0 left-0 w-24 h-24 bg-[#2C5282]/20 rounded-full -ml-12 -mb-12 blur-xl"></div>
                    
                    <h2 class="text-2xl font-bold text-white flex items-center gap-3 relative z-10">
                        <div class="p-2 bg-white/10 rounded-lg backdrop-blur-sm">
                            <Icon icon="solar:box-minimalistic-bold-duotone" class="w-7 h-7 text-[#2C5282]" />
                        </div>
                        <div>
                            تفاصيل المواد
                            <div class="text-sm font-normal text-white/70 mt-1 font-mono" v-if="selectedRequestForItems">
                                {{ selectedRequestForItems.displayId }}
                            </div>
                        </div>
                    </h2>
                    <button @click="closeItemsModal" class="text-white/70 hover:text-white hover:bg-white/10 p-2 rounded-full transition-all duration-300 relative z-10">
                        <Icon icon="mingcute:close-fill" class="w-6 h-6" />
                    </button>
                </div>

                <div class="p-8">
                    <div v-if="isItemsLoading" class="flex flex-col items-center justify-center py-12">
                         <Icon icon="svg-spinners:3-dots-fade" class="w-10 h-10 text-[#2C5282] mb-4" />
                         <span class="text-gray-500">جاري تحميل المواد...</span>
                    </div>

                    <EmptyState v-else-if="itemsData.length === 0" title="لا توجد مواد" message="القائمة فارغة لهذا الطلب" />

                    <div v-else class="space-y-4">
                         <div v-for="(item, idx) in itemsData" :key="idx" class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 flex items-center justify-between">
                             <div class="flex items-center gap-4">
                                 <div class="w-12 h-12 rounded-xl flex items-center justify-center shadow-inner" 
                                      :class="item.type === 'patient' ? 'bg-orange-50 text-orange-600' : 'bg-blue-50 text-blue-600'">
                                     <Icon :icon="item.type === 'patient' ? 'solar:user-bold' : 'solar:pill-bold'" class="w-6 h-6" />
                                 </div>
                                 <div>
                                     <div class="font-bold text-[#2C5282] text-lg">{{ item.name }}</div>
                                     <div v-if="item.details" class="text-sm text-gray-500 mt-1">{{ item.details }}</div>
                                     <div v-else-if="item.type === 'drug'" class="text-sm text-gray-500 mt-1 flex gap-2">
                                          <span>الموافقة: <b class="text-[#2C5282]">{{ item.approved_qty }}</b></span>
                                     </div>
                                 </div>
                             </div>
                             <div class="text-center bg-gray-50 px-4 py-2 rounded-xl border border-gray-200/50">
                                 <div class="text-xs text-gray-500 mb-1 font-semibold">الكمية المطلوبة</div>
                                 <div class="font-bold text-xl text-[#2C5282]">
                                     {{ item.qty }}
                                 </div>
                             </div>
                         </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Activity Details Modal -->
        <div v-if="isActivityModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
             <div @click="closeActivityModal" class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
            <div class="relative bg-[#F2F2F2] rounded-3xl shadow-2xl w-full max-w-4xl max-h-[80vh] overflow-y-auto transform transition-all scale-100" dir="rtl">
                <div class="bg-[#2C5282] px-8 py-5 flex justify-between items-center relative overflow-hidden sticky top-0 z-20">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-16 -mt-16 blur-2xl"></div>
                    <div class="absolute bottom-0 left-0 w-24 h-24 bg-[#2C5282]/20 rounded-full -ml-12 -mb-12 blur-xl"></div>
                    
                    <h2 class="text-2xl font-bold text-white flex items-center gap-3 relative z-10">
                        <div class="p-2 bg-white/10 rounded-lg backdrop-blur-sm">
                            <Icon icon="solar:history-bold-duotone" class="w-7 h-7 text-[#2C5282]" />
                        </div>
                        <div>
                            تفاصيل النشاط
                            <div class="text-sm font-normal text-white/70 mt-1 font-mono" v-if="selectedActivity">
                                {{ selectedActivity.actionArabic }} | {{ selectedActivity.createdAt }}
                            </div>
                        </div>
                    </h2>
                    <button @click="closeActivityModal" class="text-white/70 hover:text-white hover:bg-white/10 p-2 rounded-full transition-all duration-300 relative z-10">
                        <Icon icon="mingcute:close-fill" class="w-6 h-6" />
                    </button>
                </div>

                <div class="p-8">
                    <div v-if="!selectedActivity?.details?.old && !selectedActivity?.details?.new" class="flex flex-col items-center justify-center py-8">
                         <EmptyState title="لا توجد تفاصيل" message="لم يتم تسجيل تغييرات تفصيلية لهذا النشاط" />
                    </div>
                    <div v-else class="space-y-6">
                        <!-- Old Values -->
                        <div v-if="selectedActivity.details.old" class="bg-white rounded-xl overflow-hidden shadow-sm border border-red-100">
                             <div class="bg-red-50 p-4 border-b border-red-100 font-bold text-red-700 flex items-center gap-2">
                                <Icon icon="solar:rewind-back-circle-bold" class="w-5 h-5" />
                                البيانات السابقة
                             </div>
                             <div class="p-4">
                                 <div class="grid grid-cols-1 md:grid-cols-2 gap-y-4 gap-x-6">
                                     <template v-for="(val, key) in selectedActivity.details.old" :key="key">
                                         <div v-if="isVisibleField(key)" class="flex flex-col border-b border-gray-100 pb-2 last:border-0 last:pb-0">
                                             <span class="text-xs text-gray-400 font-bold mb-1 uppercase tracking-wider">{{ formatKey(key) }}</span>
                                             <span class="text-gray-700 font-medium text-sm break-all" :class="{'font-mono': key === 'password'}">
                                                 {{ formatValue(key, val) }}
                                             </span>
                                         </div>
                                     </template>
                                 </div>
                             </div>
                        </div>

                        <!-- New Values -->
                        <div v-if="selectedActivity.details.new" class="bg-white rounded-xl overflow-hidden shadow-sm border border-green-100">
                             <div class="bg-green-50 p-4 border-b border-green-100 font-bold text-green-700 flex items-center gap-2">
                                <Icon icon="solar:play-circle-bold" class="w-5 h-5" />
                                البيانات الجديدة
                             </div>
                             <div class="p-4">
                                 <div class="grid grid-cols-1 md:grid-cols-2 gap-y-4 gap-x-6">
                                     <template v-for="(val, key) in selectedActivity.details.new" :key="key">
                                         <div v-if="isVisibleField(key)" class="flex flex-col border-b border-gray-100 pb-2 last:border-0 last:pb-0">
                                             <span class="text-xs text-gray-400 font-bold mb-1 uppercase tracking-wider">{{ formatKey(key) }}</span>
                                             <span class="text-gray-700 font-bold text-sm break-all" :class="{'text-green-600': true, 'font-mono': key === 'password'}">
                                                 {{ formatValue(key, val) }}
                                             </span>
                                         </div>
                                     </template>
                                 </div>
                             </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </main>
        <!-- Departments Modal -->
        <div v-if="isDepartmentsModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div @click="closeDepartmentsModal" class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
            <div class="relative bg-[#F2F2F2] rounded-3xl shadow-2xl w-full max-w-4xl max-h-[85vh] overflow-y-auto transform transition-all scale-100" dir="rtl">
                <div class="bg-[#2C5282] px-8 py-5 flex justify-between items-center relative overflow-hidden sticky top-0 z-20">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-16 -mt-16 blur-2xl"></div>
                    <div class="absolute bottom-0 left-0 w-24 h-24 bg-[#2C5282]/20 rounded-full -ml-12 -mb-12 blur-xl"></div>
                    
                    <h2 class="text-2xl font-bold text-white flex items-center gap-3 relative z-10">
                        <div class="p-2 bg-white/10 rounded-lg backdrop-blur-sm">
                            <Icon icon="solar:hospital-bold-duotone" class="w-7 h-7 text-[#2C5282]" />
                        </div>
                        <div>
                            أقسام المستشفى
                            <div class="text-sm font-normal text-white/70 mt-1" v-if="selectedHospitalForDept">
                                {{ selectedHospitalForDept.name }}
                            </div>
                        </div>
                    </h2>
                    <button @click="closeDepartmentsModal" class="text-white/70 hover:text-white hover:bg-white/10 p-2 rounded-full transition-all duration-300 relative z-10">
                        <Icon icon="mingcute:close-fill" class="w-6 h-6" />
                    </button>
                </div>

                <div class="p-8">
                     <div v-if="isDepartmentsLoading" class="flex justify-center py-12">
                        <LoadingState message="جاري تحميل الأقسام..." />
                    </div>
                    <EmptyState v-else-if="hospitalDepartments.length === 0" title="لا توجد أقسام" message="لم يتم تسجيل أي أقسام لهذا المستشفى" />
                    <div v-else class="bg-white rounded-xl overflow-hidden shadow-sm border border-gray-100">
                        <table class="w-full text-right">
                            <thead class="bg-gray-50/50 border-b border-gray-100">
                                <tr>
                                    <th class="p-4 text-sm font-bold text-gray-600 w-1/2">القسم</th>
                                    <th class="p-4 text-sm font-bold text-gray-600">رئيس القسم</th>
                                    <th class="p-4 text-sm font-bold text-gray-600 text-center">الحالة</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <tr v-for="dept in hospitalDepartments" :key="dept.id" class="hover:bg-gray-50/50 transition-colors">
                                    <td class="p-4 font-bold text-[#2C5282]">{{ dept.name }}</td>
                                    <td class="p-4 text-gray-600">{{ dept.head }}</td>
                                    <td class="p-4 text-center">
                                        <span :class="['px-3 py-1 rounded-lg text-xs font-bold', getStatusClass(dept.status)]">
                                            {{ getStatusText(dept.status) }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pharmacies Modal -->
        <div v-if="isPharmaciesModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
             <div @click="closePharmaciesModal" class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
            <div class="relative bg-[#F2F2F2] rounded-3xl shadow-2xl w-full max-w-5xl max-h-[85vh] overflow-y-auto transform transition-all scale-100" dir="rtl">
                <div class="bg-[#2C5282] px-8 py-5 flex justify-between items-center relative overflow-hidden sticky top-0 z-20">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-16 -mt-16 blur-2xl"></div>
                    <div class="absolute bottom-0 left-0 w-24 h-24 bg-[#2C5282]/20 rounded-full -ml-12 -mb-12 blur-xl"></div>
                    
                    <h2 class="text-2xl font-bold text-white flex items-center gap-3 relative z-10">
                        <div class="p-2 bg-white/10 rounded-lg backdrop-blur-sm">
                            <Icon icon="solar:pill-bold-duotone" class="w-7 h-7 text-[#2C5282]" />
                        </div>
                        <div>
                            صيدليات المستشفى ومخزونها
                            <div class="text-sm font-normal text-white/70 mt-1" v-if="selectedHospitalForPharm">
                                {{ selectedHospitalForPharm.name }}
                            </div>
                        </div>
                    </h2>
                    <button @click="closePharmaciesModal" class="text-white/70 hover:text-white hover:bg-white/10 p-2 rounded-full transition-all duration-300 relative z-10">
                        <Icon icon="mingcute:close-fill" class="w-6 h-6" />
                    </button>
                </div>

                <div class="p-8">
                     <div v-if="isPharmaciesLoading" class="flex justify-center py-12">
                        <LoadingState message="جاري تحميل الصيدليات..." />
                    </div>
                    <EmptyState v-else-if="hospitalPharmacies.length === 0" title="لا توجد صيدليات" message="لم يتم تسجيل أي صيدليات" />
                    <div v-else class="space-y-6">
                        <div v-for="pharmacy in hospitalPharmacies" :key="pharmacy.id" class="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm">
                            <div class="bg-gray-50/80 border-b border-gray-100 p-4 flex justify-between items-center">
                                <h4 class="font-bold text-[#2C5282] flex items-center gap-2 text-lg">
                                     <Icon icon="solar:shop-bold-duotone" class="text-[#2C5282]" />
                                     {{ pharmacy.name }}
                                </h4>
                                <span class="text-xs font-bold text-[#2C5282] bg-white px-3 py-1.5 rounded-lg border border-gray-200 shadow-sm">
                                   عدد الأصناف: {{ pharmacy.inventory.length }}
                                </span>
                            </div>
                            
                            <div v-if="pharmacy.inventory.length === 0" class="p-8 text-center text-gray-500">
                                المخزون فارغ
                            </div>
                            <table v-else class="w-full text-right text-sm">
                                <thead class="bg-gray-50/30 border-b border-gray-100/50">
                                    <tr>
                                        <th class="p-4 text-gray-500 font-medium w-1/2">اسم الدواء</th>
                                        <th class="p-4 text-gray-500 font-medium">الكمية</th>
                                        <th class="p-4 text-gray-500 font-medium text-left">تاريخ الصلاحية</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    <tr v-for="(item, idx) in pharmacy.inventory" :key="idx" class="hover:bg-gray-50/30 transition-colors">
                                        <td class="p-4 font-bold text-[#4DA1A9] text-base">{{ item.drug_name }}</td>
                                        <td class="p-4 font-mono font-bold text-[#4DA1A9]">{{ item.quantity }}</td>
                                        <td class="p-4 font-mono text-left">
                                            <span v-if="item.expiry_date && item.expiry_date !== '-'" 
                                                  :class="new Date(item.expiry_date) < new Date() ? 'text-red-500 font-bold bg-red-50 px-2 py-1 rounded' : 'text-green-600 bg-green-50 px-2 py-1 rounded'">
                                                {{ item.expiry_date }}
                                            </span>
                                            <span v-else class="text-gray-400">-</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <Toast 
        :show="toast.show" 
        :type="toast.type" 
        :title="toast.title" 
        :message="toast.message" 
        @close="toast.show = false" 
    />
</DefaultLayout>
</template>

<style scoped>
/* Custom Scrollbar for tabs */
.overflow-x-auto::-webkit-scrollbar {
    height: 4px;
}
.overflow-x-auto::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 2px;
}
.overflow-x-auto::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 2px;
}
.overflow-x-auto::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}
</style>

<style>
@media print {
    @page {
        size: A4 landscape;
        margin: 10mm;
    }

    /* Print Header */
    #print-header {
        display: block !important;
        position: static !important;
        margin-bottom: 20px !important;
        page-break-after: avoid;
    }

    /* 1. Global Resets & Visibility */
    html, body {
        width: 100% !important;
        height: auto !important;
        margin: 0 !important;
        padding: 0 !important;
        background-color: white !important;
        overflow: visible !important;
        font-family: Arial, Helvetica, sans-serif !important;
        font-size: 10pt !important;
        line-height: 1.4 !important;
        color: black !important;
    }

    /* Hide standard UI elements */
    nav, aside, header, footer, .drawer-side, .drawer-toggle, .no-print, button, input, select, textarea {
        display: none !important;
    }

    /* 2. Layout Container Resets (Fixes Blank Page) */
    /* Target common layout wrappers like #app, .drawer, .drawer-content */
    #app, .drawer, .drawer-content {
        display: block !important; /* Override flex/grid that might collapse */
        width: 100% !important;
        height: auto !important;
        position: static !important;
        overflow: visible !important;
        background-color: white !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    /* Reset Main Content Area */
    main {
        display: block !important;
        width: 100% !important;
        height: auto !important;
        min-height: 0 !important;
        margin: 0 !important;
        padding: 0 !important;
        overflow: visible !important;
        background-color: white !important;
        border: none !important;
    }

    /* 3. Component & Utility Overrides */
    
    /* Expand scrollable areas */
    .overflow-x-auto, .overflow-y-auto, .overflow-hidden {
        overflow: visible !important;
        height: auto !important;
        max-height: none !important;
    }

    /* Hide Scrollbars */
    ::-webkit-scrollbar {
        display: none !important;
    }

    /* Hide Modals */
    .fixed.inset-0 { 
        display: none !important; 
    }

    /* 4. Visual Styling (White Paper, Dark Text) */
    
    /* Force white backgrounds */
    * {
        background-color: white !important;
        background-image: none !important;
    }

    /* Force dark text */
    body, p, div, span, td, th, h1, h2, h3, h4, li, ul, ol {
        color: black !important;
        fill: black !important; /* For icons */
    }

    /* 5. Table Styling */
    table {
        width: 100% !important;
        border-collapse: collapse !important;
        font-size: 9pt !important;
        background-color: white !important;
        margin-bottom: 20px !important;
        page-break-inside: avoid;
    }
    
    th, td {
        border: 1px solid #ccc !important;
        padding: 6px 8px !important;
        text-align: right !important;
        vertical-align: top !important;
    }

    th {
        background-color: #f5f5f5 !important;
        border-bottom: 2px solid #4DA1A9 !important;
        font-weight: bold !important;
        font-size: 10pt !important;
    }

    tbody tr:nth-child(even) {
        background-color: #fafafa !important;
    }

    /* 6. Page Breaks */
    .page-break {
        page-break-before: always;
    }

    .no-page-break {
        page-break-inside: avoid;
    }

    /* Avoid breaking rows across pages */
    tr {
        page-break-inside: avoid;
        page-break-after: auto;
    }

    /* 7. Typography */
    h1, h2, h3, h4, h5, h6 {
        color: #4DA1A9 !important;
        font-weight: bold !important;
        margin: 10px 0 !important;
        page-break-after: avoid;
    }

    p {
        margin: 5px 0 !important;
    }

    /* 8. Spacing */
    .bg-white.rounded-3xl {
        margin: 0 !important;
        padding: 0 !important;
        border: none !important;
        box-shadow: none !important;
    }

    .p-6 {
        padding: 0 !important;
    }

    /* 9. Print Logic Helpers */
    .print-row {
        display: table-row !important;
        background-color: white !important;
    }
    
    .print-row td {
        border-top: none !important;
        padding: 0 8px 8px 8px !important;
    }

    /* Print Summary Styling */
    .print-summary {
        background-color: #f9f9f9 !important;
        border: 1px solid #ddd !important;
        page-break-inside: avoid;
        margin-bottom: 20px !important;
    }

    .print-summary h3 {
        color: #4DA1A9 !important;
        font-size: 14pt !important;
        margin-bottom: 10px !important;
    }

    .print-summary .grid {
        display: table !important;
        width: 100% !important;
    }

    .print-summary .grid > div {
        display: table-cell !important;
        width: 25% !important;
        padding: 10px !important;
        vertical-align: top !important;
    }

    /* Enhanced Dispensings Print Layout */
    .print-only-dispensings {
        display: block !important;
        background-color: white !important;
        color: black !important;
        font-family: 'Arial', sans-serif !important;
        font-size: 12pt !important;
        line-height: 1.4 !important;
        padding: 0 !important;
        margin: 0 !important;
    }

    .print-header {
        text-align: center !important;
        border-bottom: 3px solid #4DA1A9 !important;
        padding-bottom: 15px !important;
        margin-bottom: 20px !important;
        page-break-after: avoid !important;
    }

    .print-title {
        color: #4DA1A9 !important;
        font-size: 20pt !important;
        font-weight: bold !important;
        margin: 0 0 10px 0 !important;
    }

    .print-meta {
        display: flex !important;
        justify-content: center !important;
        gap: 20px !important;
        font-size: 10pt !important;
        color: #666 !important;
        flex-wrap: wrap !important;
    }

    .print-summary {
        background-color: #f8f9fa !important;
        border: 2px solid #4DA1A9 !important;
        border-radius: 8px !important;
        padding: 15px !important;
        margin-bottom: 25px !important;
        page-break-inside: avoid !important;
    }

    .print-summary h2 {
        color: #4DA1A9 !important;
        font-size: 16pt !important;
        font-weight: bold !important;
        margin: 0 0 15px 0 !important;
        text-align: center !important;
        border-bottom: 1px solid #4DA1A9 !important;
        padding-bottom: 5px !important;
    }

    .summary-grid {
        display: table !important;
        width: 100% !important;
        border-collapse: separate !important;
        border-spacing: 10px !important;
    }

    .summary-item {
        display: table-cell !important;
        background-color: white !important;
        border: 1px solid #ddd !important;
        border-radius: 6px !important;
        padding: 15px !important;
        text-align: center !important;
        vertical-align: middle !important;
        width: 25% !important;
    }

    .summary-value {
        font-size: 18pt !important;
        font-weight: bold !important;
        color: #4DA1A9 !important;
        margin-bottom: 5px !important;
    }

    .summary-label {
        font-size: 9pt !important;
        color: #666 !important;
        font-weight: normal !important;
    }

    .operations-details {
        margin-top: 20px !important;
    }

    .operations-details h2 {
        color: #4DA1A9 !important;
        font-size: 16pt !important;
        font-weight: bold !important;
        margin: 20px 0 15px 0 !important;
        border-bottom: 2px solid #4DA1A9 !important;
        padding-bottom: 5px !important;
    }

    .operation-card {
        border: 1px solid #ddd !important;
        border-radius: 8px !important;
        margin-bottom: 20px !important;
        background-color: white !important;
        page-break-inside: avoid !important;
    }

    .operation-header {
        background-color: #4DA1A9 !important;
        color: white !important;
        padding: 10px 15px !important;
        display: flex !important;
        justify-content: space-between !important;
        align-items: center !important;
        border-radius: 8px 8px 0 0 !important;
    }

    .operation-number {
        font-weight: bold !important;
        font-size: 12pt !important;
    }

    .operation-status {
        background-color: rgba(255,255,255,0.2) !important;
        padding: 3px 8px !important;
        border-radius: 4px !important;
        font-size: 9pt !important;
    }

    .operation-info {
        padding: 15px !important;
        background-color: #f8f9fa !important;
    }

    .info-row {
        display: flex !important;
        justify-content: space-between !important;
        margin-bottom: 8px !important;
        padding: 5px 0 !important;
        border-bottom: 1px solid #eee !important;
    }

    .info-row:last-child {
        border-bottom: none !important;
        margin-bottom: 0 !important;
    }

    .info-label {
        font-weight: bold !important;
        color: #333 !important;
        min-width: 100px !important;
    }

    .info-value {
        color: #666 !important;
        text-align: right !important;
    }

    .medications-table {
        padding: 15px !important;
    }

    .medications-table h3 {
        color: #4DA1A9 !important;
        font-size: 12pt !important;
        font-weight: bold !important;
        margin: 0 0 10px 0 !important;
    }

    .medications-table-content {
        width: 100% !important;
        border-collapse: collapse !important;
        font-size: 9pt !important;
        background-color: white !important;
    }

    .medications-table-content th {
        background-color: #4DA1A9 !important;
        color: white !important;
        padding: 8px !important;
        text-align: center !important;
        font-weight: bold !important;
        border: 1px solid #ddd !important;
    }

    .medications-table-content td {
        padding: 6px 8px !important;
        border: 1px solid #ddd !important;
        text-align: center !important;
    }

    .medications-table-content .drug-name {
        text-align: right !important;
        font-weight: bold !important;
    }

    .medications-table-content .quantity {
        font-weight: bold !important;
        color: #4DA1A9 !important;
    }

    .simple-medication {
        padding: 15px !important;
        background-color: #f8f9fa !important;
        border-top: 1px solid #ddd !important;
    }

    .medication-item {
        display: flex !important;
        justify-content: space-between !important;
        align-items: center !important;
        padding: 8px 0 !important;
    }

    .medication-item .drug-name {
        font-weight: bold !important;
        color: #4DA1A9 !important;
    }

    .medication-item .quantity {
        color: #666 !important;
        font-weight: bold !important;
    }

    .print-footer {
        margin-top: 30px !important;
        text-align: center !important;
        font-size: 9pt !important;
        color: #666 !important;
        border-top: 1px solid #ddd !important;
        padding-top: 15px !important;
    }

    .footer-line {
        height: 2px !important;
        background-color: #4DA1A9 !important;
        margin-bottom: 10px !important;
    }
}
</style>
