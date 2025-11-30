<script setup>
import { ref, computed } from "vue";
import { Icon } from "@iconify/vue";

import Navbar from "@/components/Navbar.vue";
import Sidebar from "@/components/Sidebar.vue";
import search from "@/components/search.vue";
import btnprint from "@/components/btnprint.vue";
import DrugPreviewModal from "@/components/forpharmacist/DrugPreviewModal.vue";
import SupplyRequestModal from "@/components/forpharmacist/SupplyRequestModal.vue";

// ----------------------------------------------------
// 1. بيانات الأدوية
// ----------------------------------------------------
const drugsData = ref([
    {
        drugCode: "MET-500",
        drugName: "Glucophage",
        quantity: 3,
        expiryDate: "2028/10/22",
        requestStatus: "تم الطلب",
        actionIcon: "tabler:eye-minus",
        scientificName: "Metformin Hydrochloride",
        therapeuticClass: "أدوية علاج السكري",
        pharmaceuticalForm: "أقراص فموية",
        manufacturer: "Merck",
        mfgCountry: "فرنسا",
        indications:
            "يستخدم للتحكم في مستوى السكر المرتفع في الدم لدى مرضى السكري من النوع الثاني",
        instructions:
            "يؤخذ عادة مع وجبات الطعام لتقليل اضطرابات المعدة. يجب اتباع تعليمات الطبيب بدقة بخصوص الجرعة",
        warnings:
            "يجب إبلاغ الطبيب في حال وجود أمراض في الكلى أو الكبد. لا يستخدم أثناء الحمل إلا باستشارة طبية",
        lastUpdate: "2025/12/09",
    },
    {
        drugCode: "AML-010",
        drugName: "Amlodipine 10mg",
        quantity: 8,
        expiryDate: "2026/12/05",
        requestStatus: "تم الطلب",
        actionIcon: "tabler:eye-minus",
        scientificName: "Amlodipine Besylate",
        therapeuticClass: "خافض ضغط (حاصرات قنوات الكالسيوم)",
        pharmaceuticalForm: "أقراص",
        manufacturer: "Pharma Co.",
        mfgCountry: "المملكة المتحدة",
        indications: "لعلاج ارتفاع ضغط الدم والذبحة الصدرية.",
        instructions: "يؤخذ مرة واحدة يوميًا، ويفضل في نفس الوقت.",
        warnings:
            "قد يسبب تورم في الكاحلين أو الصداع. يجب استشارة الطبيب قبل التوقف عن استخدامه.",
        lastUpdate: "2024/05/10",
    },
    {
        drugCode: "ATV-020",
        drugName: "Atorvastatin 20mg",
        quantity: 10,
        expiryDate: "2026/08/05",
        requestStatus: "قيد التجهيز",
        actionIcon: "tabler:eye-minus",
        scientificName: "Atorvastatin Calcium",
        therapeuticClass: "ستاتينات (لتخفيض الكوليسترول)",
        pharmaceuticalForm: "أقراص",
        manufacturer: "Global Health",
        mfgCountry: "الهند",
        indications: "يستخدم لخفض مستويات الكوليسترول الضار والدهون الثلاثية.",
        instructions: "يؤخذ مرة واحدة يومياً، ويفضل في المساء.",
        warnings:
            "قد يسبب آلاماً في العضلات. يجب إبلاغ الطبيب فوراً في حال الشعور بألم غير عادي.",
        lastUpdate: "2025/01/20",
    },
    {
        drugCode: "GLI-080",
        drugName: "Gliclazide 80mg",
        quantity: 14,
        expiryDate: "2026/08/10",
        requestStatus: "لم يتم الطلب",
        actionIcon: "tabler:eye-minus",
        scientificName: "Gliclazide",
        therapeuticClass: "أدوية علاج السكري",
        pharmaceuticalForm: "أقراص",
        manufacturer: "Sanofi",
        mfgCountry: "إيطاليا",
        indications:
            "يستخدم للتحكم في سكر الدم لدى مرضى السكري من النوع الثاني.",
        instructions: "يؤخذ قبل الوجبة بفترة وجيزة.",
        warnings:
            "يجب تجنب القيادة أو تشغيل الآلات في حال الشعور بأعراض انخفاض سكر الدم (الغثيان، التعرق).",
        lastUpdate: "2024/11/01",
    },
    {
        drugCode: "LIS-005",
        drugName: "Lisinopril 5mg",
        quantity: 70,
        expiryDate: "2028/08/09",
        requestStatus: "لم يتم الطلب",
        actionIcon: "tabler:eye-minus",
        scientificName: "Lisinopril",
        therapeuticClass: "مثبطات الإنزيم المحول للأنجيوتنسين (ACE)",
        pharmaceuticalForm: "أقراص",
        manufacturer: "Zydus",
        mfgCountry: "الإمارات",
        indications: "لعلاج ارتفاع ضغط الدم وقصور القلب.",
        instructions: "يؤخذ مرة واحدة يوميًا.",
        warnings: "قد يسبب سعالاً جافاً. يحظر استخدامه أثناء الحمل.",
        lastUpdate: "2023/10/01",
    },
    {
        drugCode: "SAL-INH",
        drugName: "Salbutamol Inhaler",
        quantity: 21,
        expiryDate: "2026/04/12",
        requestStatus: "لم يتم الطلب",
        actionIcon: "tabler:eye-minus",
        scientificName: "Salbutamol Sulfate",
        therapeuticClass: "موسعات القصبات الهوائية",
        pharmaceuticalForm: "جهاز استنشاق",
        manufacturer: "Bayer",
        mfgCountry: "ألمانيا",
        indications: "يستخدم لتخفيف أعراض الربو وضيق التنفس.",
        instructions: "يستخدم عند اللزوم، أو حسب تعليمات الطبيب.",
        warnings: "قد يسبب خفقان في القلب. لا تتجاوز الجرعة الموصوفة.",
        lastUpdate: "2024/08/05",
    },
    {
        drugCode: "PAR-500",
        drugName: "Paracetamol 500mg",
        quantity: 20,
        expiryDate: "2029/01/01",
        requestStatus: "لم يتم الطلب",
        actionIcon: "tabler:eye-minus",
        scientificName: "Paracetamol",
        therapeuticClass: "مسكن للألم وخافض للحرارة",
        pharmaceuticalForm: "أقراص",
        manufacturer: "GSK",
        mfgCountry: "مصر",
        indications: "لتخفيف الآلام الخفيفة والمتوسطة وخفض الحمى.",
        instructions: "لا تتجاوز الجرعة اليومية الموصى بها.",
        warnings: "تجنب الاستخدام المفرط لتجنب تلف الكبد.",
        lastUpdate: "2024/09/25",
    },
    {
        drugCode: "OME-020",
        drugName: "Omeprazole 20mg",
        quantity: 1,
        expiryDate: "2027/07/17",
        requestStatus: "لم يتم الطلب",
        actionIcon: "tabler:eye-minus",
        scientificName: "Omeprazole",
        therapeuticClass: "مثبطات مضخة البروتون (PPI)",
        pharmaceuticalForm: "كبسولات",
        manufacturer: "AstraZeneca",
        mfgCountry: "كندا",
        indications: "لعلاج حرقة المعدة وقرحة المعدة والمريء.",
        instructions: "يؤخذ قبل الوجبة بـ 30 دقيقة.",
        warnings: "يجب استشارة الطبيب قبل الاستخدام طويل الأمد.",
        lastUpdate: "2025/03/03",
    },
    {
        drugCode: "WAR-005",
        drugName: "Warfarin 5mg",
        quantity: 103,
        expiryDate: "2026/03/16",
        requestStatus: "لم يتم الطلب",
        actionIcon: "tabler:eye-minus",
        scientificName: "Warfarin Sodium",
        therapeuticClass: "مضاد تخثر (مميع للدم)",
        pharmaceuticalForm: "أقراص",
        manufacturer: "Bristol-Myers Squibb",
        mfgCountry: "سويسرا",
        indications: "للوقاية من الجلطات الدموية.",
        instructions: "يؤخذ في نفس الوقت يوميًا. يلزم متابعة تحليل INR.",
        warnings: "يجب تجنب الأطعمة الغنية بفيتامين K. يزيد من خطر النزيف.",
        lastUpdate: "2024/07/15",
    },
    {
        drugCode: "AMX-500",
        drugName: "Amoxicillin 500mg",
        quantity: 22,
        expiryDate: "2027/08/20",
        requestStatus: "لم يتم الطلب",
        actionIcon: "tabler:eye-minus",
        scientificName: "Amoxicillin Trihydrate",
        therapeuticClass: "مضاد حيوي (بنسلين)",
        pharmaceuticalForm: "كبسولات",
        manufacturer: "Hikma",
        mfgCountry: "الأردن",
        indications: "لعلاج الالتهابات البكتيرية المختلفة.",
        instructions: "يؤخذ كل 8 أو 12 ساعة حسب الوصفة.",
        warnings:
            "في حال حدوث طفح جلدي أو ضيق تنفس، يجب التوقف فوراً واستشارة الطبيب (حساسية).",
        lastUpdate: "2024/02/29",
    },
]);

const categories = ref([
    { id: 1, name: "مسكنات الألم" },
    { id: 2, name: "أدوية الضغط" },
    { id: 3, name: "مضادات حيوية" },
    { id: 4, name: "السكري" },
    { id: 5, name: "الكوليسترول" },
    { id: 6, name: "عام" },
]);

const allDrugsData = [
    {
        id: 101,
        name: "بروفين",
        categoryId: 1,
        dosage: "200 مجم",
        type: "Tablet",
    },
    {
        id: 102,
        name: "بروفين",
        categoryId: 1,
        dosage: "400 مجم",
        type: "Tablet",
    },
    {
        id: 103,
        name: "فولتارين",
        categoryId: 1,
        dosage: "50 مجم",
        type: "Tablet",
    },
    {
        id: 401,
        name: "مضاد حيوي شراب",
        categoryId: 3,
        dosage: "50 مل/100 مل",
        type: "Liquid",
    },
    {
        id: 402,
        name: "باراسيتامول شراب",
        categoryId: 1,
        dosage: "120 مل/5 مل",
        type: "Liquid",
    },
    {
        id: 1,
        name: "إنالابريل",
        categoryId: 2,
        dosage: "5 مجم",
        type: "Tablet",
    },
    {
        id: 41,
        name: "دواء السعال",
        categoryId: 6,
        dosage: "100 مل",
        type: "Liquid",
    },
    {
        id: 403,
        name: "Metformin",
        categoryId: 4,
        dosage: "500 مجم",
        type: "Tablet",
    },
    {
        id: 404,
        name: "Insulin Glargine",
        categoryId: 4,
        dosage: "100 وحدة/مل",
        type: "Liquid",
    },
    {
        id: 405,
        name: "Atorvastatin",
        categoryId: 5,
        dosage: "10 مجم",
        type: "Tablet",
    },
];

// ----------------------------------------------------
// 2. حالة المكونات المنبثقة
// ----------------------------------------------------
const isDrugPreviewModalOpen = ref(false);
const isSupplyRequestModalOpen = ref(false);
const selectedDrug = ref({});

// ----------------------------------------------------
// 3. منطق البحث والفرز
// ----------------------------------------------------
const searchTerm = ref("");
const sortKey = ref("quantity");
const sortOrder = ref("asc");

const sortDrugs = (key, order) => {
    sortKey.value = key;
    sortOrder.value = order;
};

const filteredDrugss = computed(() => {
    // 1. التصفية
    let list = drugsData.value;
    if (searchTerm.value) {
        const search = searchTerm.value.toLowerCase();
        list = list.filter(
            (drug) =>
                drug.drugCode.toLowerCase().includes(search) ||
                drug.drugName.toLowerCase().includes(search) ||
                drug.requestStatus.includes(search)
        );
    }

    // 2. الفرز
    if (sortKey.value) {
        list.sort((a, b) => {
            let comparison = 0;

            if (sortKey.value === "drugName") {
                comparison = a.drugName.localeCompare(b.drugName, "ar");
            } else if (sortKey.value === "quantity") {
                comparison = a.quantity - b.quantity;
            } else if (sortKey.value === "expiryDate") {
                const dateA = new Date(a.expiryDate.replace(/\//g, "-"));
                const dateB = new Date(b.expiryDate.replace(/\//g, "-"));
                comparison = dateA.getTime() - dateB.getTime();
            }

            return sortOrder.value === "asc" ? comparison : -comparison;
        });
    }

    return list;
});

// ----------------------------------------------------
// 4. دالة تحديد لون الصف والخط
// ----------------------------------------------------
const getRowColorClass = (quantity) => {
    if (quantity < 10) {
        return " bg-red-50/70 border-r-4 ";
    }
    else if (quantity >= 10 && quantity <= 20) {
        return "bg-yellow-50/70 border-r-4 border-yellow-500";
    }
    else {
        return "bg-white border-gray-300 border";
    }
};

const getTextColorClass = (quantity) => {
    if (quantity < 10) {
        return "text-red-700 font-semibold";
    } else if (quantity >= 10 && quantity <= 20) {
        return "text-yellow-700 font-semibold";
    } else {
        return "text-gray-800";
    }
};

// ----------------------------------------------------
// 5. وظائف العرض
// ----------------------------------------------------
const showDrugDetails = (drug) => {
    selectedDrug.value = drug;
    isDrugPreviewModalOpen.value = true;
};

const openSupplyRequestModal = () => {
    isSupplyRequestModalOpen.value = true;
};

const closeSupplyRequestModal = () => {
    isSupplyRequestModalOpen.value = false;
};

const handleSupplyConfirm = (dosageList) => {
    showSuccessAlert(`✅ تم تأكيد إسناد ${dosageList.length} دواء بنجاح!`);
    console.log("تم تأكيد إضافة الجرعات اليومية:", dosageList);
};

// ----------------------------------------------------
// 6. منطق الطباعة
// ----------------------------------------------------
const printTable = () => {
    const resultsCount = filteredDrugss.value.length;

    const printWindow = window.open("", "_blank", "height=600,width=800");

    if (
        !printWindow ||
        printWindow.closed ||
        typeof printWindow.closed === "undefined"
    ) {
        showSuccessAlert(
            "❌ فشل عملية الطباعة. يرجى السماح بفتح النوافذ المنبثقة لهذا الموقع."
        );
        return;
    }

    let tableHtml = `
<style>
body { font-family: 'Arial', sans-serif; direction: rtl; padding: 20px; }
table { width: 100%; border-collapse: collapse; margin-top: 15px; }
th, td { border: 1px solid #ccc; padding: 10px; text-align: right; }
th { background-color: #f2f2f2; font-weight: bold; }
h1 { text-align: center; color: #2E5077; margin-bottom: 10px; }
.results-info { text-align: right; margin-bottom: 15px; font-size: 16px; font-weight: bold; color: #4DA1A9; }
</style>

<h1>قائمة الأدوية (تقرير طباعة)</h1>

<p class="results-info">عدد النتائج التي ظهرت (عدد الصفوف): ${resultsCount}</p>

<table>
<thead>
 <tr>
 <th>رمز الدواء</th>
 <th>اسم الدواء</th>
 <th>الكمية</th>
 <th>تاريخ إنتهاء الصلاحية</th>
 <th>حالة الطلب</th>
 </tr>
</thead>
<tbody>
`;

    filteredDrugss.value.forEach((drug) => {
        tableHtml += `
<tr>
 <td>${drug.drugCode}</td>
 <td>${drug.drugName}</td>
 <td>${drug.quantity}</td>
 <td>${drug.expiryDate}</td>
 <td>${drug.requestStatus}</td>
</tr>
`;
    });

    tableHtml += `
</tbody>
</table>
`;

    printWindow.document.write(
        "<html><head><title>طباعة قائمة الأدوية</title>"
    );
    printWindow.document.write("</head><body>");
    printWindow.document.write(tableHtml);
    printWindow.document.write("</body></html>");
    printWindow.document.close();

    printWindow.onload = () => {
        printWindow.focus();
        printWindow.print();
        showSuccessAlert("✅ تم تجهيز التقرير بنجاح للطباعة.");
    };
};

// ----------------------------------------------------
// 7. نظام التنبيهات
// ----------------------------------------------------
const isSuccessAlertVisible = ref(false);
const successMessage = ref("");
let alertTimeout = null;

const showSuccessAlert = (message) => {
    if (alertTimeout) {
        clearTimeout(alertTimeout);
    }

    successMessage.value = message;
    isSuccessAlertVisible.value = true;

    alertTimeout = setTimeout(() => {
        isSuccessAlertVisible.value = false;
        successMessage.value = "";
    }, 4000);
};
</script>

<template>
    <div class="drawer lg:drawer-open" dir="rtl">
        <input id="my-drawer" type="checkbox" class="drawer-toggle" checked />

        <div class="drawer-content flex flex-col bg-gray-50 min-h-screen">
            <Navbar />

            <main class="flex-1 p-4 sm:p-5 pt-3">
                <div
                    class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-3 sm:gap-0"
                >
                    <div class="flex items-center gap-3 w-full sm:max-w-xl">
                        <div class="relative w-full sm:max-w-sm">
                            <search v-model="searchTerm" />
                        </div>

                        <div class="dropdown dropdown-start">
                            <div
                                tabindex="0"
                                role="button"
                                class=" inline-flex items-center px-[11px] py-[9px] border-2 border-[#ffffff8d] h-11 w-23 rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden text-[15px] cursor-pointer text-white z-[1] bg-[#4DA1A9] hover:border hover:border-[#a8a8a8] hover:bg-[#5e8c90f9]"
                            >
                                <Icon
                                    icon="lucide:arrow-down-up"
                                    class="w-5 h-5 ml-2"
                                />
                                فرز
                            </div>
                            <ul
                                tabindex="0"
                                class="dropdown-content z-[50] menu p-2 shadow-lg bg-white border-2 hover:border hover:border-[#a8a8a8] border-[#ffffff8d] rounded-[35px] w-52 text-right"
                            >
                                <li
                                    class="menu-title text-gray-700 font-bold text-sm"
                                >
                                    حسب اسم الدواء:
                                </li>
                                <li>
                                    <a
                                        @click="sortDrugs('drugName', 'asc')"
                                        :class="{
                                            'font-bold text-[#4DA1A9]':
                                                sortKey === 'drugName' &&
                                                sortOrder === 'asc',
                                        }"
                                    >
                                        الاسم (أ - ي)
                                    </a>
                                </li>
                                <li>
                                    <a
                                        @click="sortDrugs('drugName', 'desc')"
                                        :class="{
                                            'font-bold text-[#4DA1A9]':
                                                sortKey === 'drugName' &&
                                                sortOrder === 'desc',
                                        }"
                                    >
                                        الاسم (ي - أ)
                                    </a>
                                </li>

                                <li
                                    class="menu-title text-gray-700 font-bold text-sm mt-2"
                                >
                                    حسب الكمية:
                                </li>
                                <li>
                                    <a
                                        @click="sortDrugs('quantity', 'asc')"
                                        :class="{
                                            'font-bold text-[#4DA1A9]':
                                                sortKey === 'quantity' &&
                                                sortOrder === 'asc',
                                        }"
                                    >
                                        الأقل كمية أولاً (تنبيه)
                                    </a>
                                </li>
                                <li>
                                    <a
                                        @click="sortDrugs('quantity', 'desc')"
                                        :class="{
                                            'font-bold text-[#4DA1A9]':
                                                sortKey === 'quantity' &&
                                                sortOrder === 'desc',
                                        }"
                                    >
                                        الأعلى كمية أولاً
                                    </a>
                                </li>

                                <li
                                    class="menu-title text-gray-700 font-bold text-sm mt-2"
                                >
                                    حسب تاريخ الإنتهاء:
                                </li>
                                <li>
                                    <a
                                        @click="sortDrugs('expiryDate', 'asc')"
                                        :class="{
                                            'font-bold text-[#4DA1A9]':
                                                sortKey === 'expiryDate' &&
                                                sortOrder === 'asc',
                                        }"
                                    >
                                        الأقرب إنتهاءً
                                    </a>
                                </li>
                                <li>
                                    <a
                                        @click="sortDrugs('expiryDate', 'desc')"
                                        :class="{
                                            'font-bold text-[#4DA1A9]':
                                                sortKey === 'expiryDate' &&
                                                sortOrder === 'desc',
                                        }"
                                    >
                                        الأبعد إنتهاءً
                                    </a>
                                </li>
                            </ul>
                        </div>
                        <p
                            class="text-sm font-semibold text-gray-600 self-end sm:self-center"
                        >
                            عدد النتائج :
                            <span class="text-[#4DA1A9] text-lg font-bold">{{
                                filteredDrugss.length
                            }}</span>
                        </p>
                    </div>

                    <div
                        class="flex items-center gap-5 w-full sm:w-auto justify-end"
                    >
                        <button
                            class=" inline-flex items-center px-[11px] py-[9px] border-2 border-[#ffffff8d] h-11 w-29 rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden text-[15px] cursor-pointer text-white z-[1] bg-[#4DA1A9] hover:border hover:border-[#a8a8a8] hover:bg-[#5e8c90f9]"
                            @click="openSupplyRequestModal"
                        >
                            طلب التوريد
                        </button>
                        <btnprint @click="printTable" />
                    </div>
                </div>

                <div
                    class="bg-white rounded-2xl shadow h-107 overflow-hidden flex flex-col"
                >
                    <div
                        class="overflow-y-auto flex-1"
                        style="
                            scrollbar-width: auto;
                            scrollbar-color: grey transparent;
                            direction: ltr;
                        "
                    >
                        <div class="overflow-x-auto h-full">
                            <table
                                dir="rtl"
                                class="table w-full text-right min-w-[700px] border-collapse"
                            >
                                <thead
                                    class="bg-[#9aced2] text-black sticky top-0 z-10 border-b border-gray-300"
                                >
                                    <tr>
                                        <th class="drug-code-col">
                                            رمز الدواء
                                        </th>
                                        <th class="drug-name-col">
                                            اسم الدواء
                                        </th>
                                        <th class="quantity-col">الكمية</th>
                                        <th class="expiry-date-col">
                                            تاريخ إنتهاء الصلاحية
                                        </th>
                                        <th class="status-col">حالة الطلب</th>
                                        <th class="actions-col">الإجراءات</th>
                                    </tr>
                                </thead>

                                <tbody class="text-gray-800">
                                    <tr
                                        v-for="(drug, index) in filteredDrugss"
                                        :key="index"
                                        :class="[
                                            'hover:bg-gray-100',
                                            getRowColorClass(drug.quantity),
                                        ]"
                                    >
                                        <td
                                            :class="
                                                getTextColorClass(drug.quantity)
                                            "
                                        >
                                            {{ drug.drugCode }}
                                        </td>
                                        <td
                                            :class="
                                                getTextColorClass(drug.quantity)
                                            "
                                        >
                                            {{ drug.drugName }}
                                        </td>
                                        <td
                                            :class="
                                                getTextColorClass(drug.quantity)
                                            "
                                        >
                                            <span class="font-bold">{{
                                                drug.quantity
                                            }}</span>
                                        </td>
                                        <td
                                            :class="
                                                getTextColorClass(drug.quantity)
                                            "
                                        >
                                            {{ drug.expiryDate }}
                                        </td>
                                        <td
                                            :class="[
                                                getTextColorClass(
                                                    drug.quantity
                                                ),
                                                {
                                                    'font-semibold': true,
                                                    'text-red-700':
                                                        drug.requestStatus ===
                                                            'تم الطلب' &&
                                                        drug.quantity > 20,
                                                    'text-yellow-700':
                                                        drug.requestStatus ===
                                                            'قيد التجهيز' &&
                                                        drug.quantity > 20,
                                                    'text-green-700':
                                                        drug.requestStatus ===
                                                            'لم يتم الطلب' &&
                                                        drug.quantity > 20,
                                                },
                                            ]"
                                        >
                                            {{ drug.requestStatus }}
                                        </td>
                                        <td class="actions-col">
                                            <div
                                                class="flex gap-3 justify-center"
                                            >
                                                <button
                                                    @click="
                                                        showDrugDetails(drug)
                                                    "
                                                >
                                                    <Icon
                                                        :icon="drug.actionIcon"
                                                        :class="[
                                                            'w-5 h-5 cursor-pointer hover:scale-110 transition-transform text-green-700',
                                                        ]"
                                                    />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>

        <Sidebar />

        <!-- مكونات النماذج المنبثقة -->
        <DrugPreviewModal 
            :is-open="isDrugPreviewModalOpen"
            :drug="selectedDrug"
            @close="isDrugPreviewModalOpen = false"
        />

        <SupplyRequestModal
            :is-open="isSupplyRequestModalOpen"
            :categories="categories"
            :all-drugs-data="allDrugsData"
            :drugs-data="drugsData"
            @close="closeSupplyRequestModal"
            @confirm="handleSupplyConfirm"
            @show-alert="showSuccessAlert"
        />

        <!-- تنبيه النجاح -->
        <Transition
            enter-active-class="transition duration-300 ease-out transform"
            enter-from-class="translate-x-full opacity-0"
            enter-to-class="translate-x-0 opacity-100"
            leave-active-class="transition duration-200 ease-in transform"
            leave-from-class="translate-x-0 opacity-100"
            leave-to-class="translate-x-full opacity-0"
        >
            <div
                v-if="isSuccessAlertVisible"
                class="fixed top-4 right-55 z-[1000] p-4 text-right bg-green-500 text-white rounded-lg shadow-xl max-w-xs transition-all duration-300"
                dir="rtl"
            >
                {{ successMessage }}
            </div>
        </Transition>
    </div>
</template>

<style>
/* تنسيقات شريط التمرير */
::-webkit-scrollbar {
    width: 8px;
}
::-webkit-scrollbar-track {
    background: transparent;
}
::-webkit-scrollbar-thumb {
    background-color: #4da1a9;
    border-radius: 10px;
}
::-webkit-scrollbar-thumb:hover {
    background-color: #3a8c94;
}

/* تنسيقات عرض أعمدة الجدول */
.actions-col {
    width: 120px;
    min-width: 120px;
    max-width: 120px;
    text-align: center;
    padding-left: 0.5rem;
    padding-right: 0.5rem;
}
.drug-code-col {
    width: 120px;
    min-width: 120px;
}
.quantity-col {
    width: 90px;
    min-width: 90px;
}
.expiry-date-col {
    width: 150px;
    min-width: 150px;
}
.status-col {
    width: 140px;
    min-width: 140px;
}
.drug-name-col {
    width: auto;
    min-width: 170px;
}
</style>