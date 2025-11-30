<!-- components/SupplyRequestModal.vue -->
<template>
    <div
        v-if="isOpen"
        class="fixed inset-0 z-50 overflow-y-auto bg-black/50 bg-opacity-50 flex items-center justify-center p-4"
    >
        <div
            class="relative bg-[#F6F4F0] rounded-xl shadow-2xl w-full max-w-2xl mx-auto my-10 transform transition-all duration-300 scale-100 opacity-100"
            role="dialog"
            aria-modal="true"
            aria-labelledby="modal-title"
        >
            <div
                class="flex justify-between items-center bg-[#F6F4F0] p-4 sm:p-6 border-b border-[#B8D7D9] sticky top-0 bg-white rounded-t-xl z-10"
            >
                <h3
                    id="modal-title"
                    class="text-xl font-bold text-[#2E5077] flex items-center"
                >
                    <Icon
                        icon="tabler:medical-cross"
                        class="w-6 h-6 ml-2 text-[#4DA1A9]"
                    />
                    طلب توريد
                </h3>
                <button
                    @click="closeModal"
                    class="text-gray-400 hover:text-gray-600 transition duration-150"
                >
                    <Icon icon="tabler:x" class="w-6 h-6" />
                </button>
            </div>

            <div class="p-4 sm:pr-6 sm:pl-6 space-y-6">
                <div class="space-y-4">
                    <h3
                        class="text-lg font-semibold text-[#4DA1A9] border-b border-dashed border-[#B8D7D9] pb-1 flex items-center"
                    >
                        <Icon
                            icon="tabler:medical-cross"
                            class="w-5 h-5 ml-2"
                        />
                        معلومات الدواء
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="flex flex-col gap-1 sm:col-span-1">
                            <label
                                for="drugCategory"
                                class="text-right font-medium text-[#2E5077] pt-2"
                                >الفئة:</label
                            >
                            <select
                                id="drugCategory"
                                v-model="selectedCategory"
                                @change="handleInput"
                                class="h-11 p-2.5 px-4 border border-[#B8D7D9] rounded-2xl text-base w-full transition duration-200 focus:border-[#4DA1A9] focus:ring-1 focus:ring-[#4DA1A9] bg-white cursor-pointer shadow-none text-gray-700"
                            >
                                <option value="">كل الفئات</option>
                                <option
                                    v-for="cat in categories"
                                    :key="cat.id"
                                    :value="cat.id"
                                >
                                    {{ cat.name }}
                                </option>
                            </select>
                        </div>

                        <div class="flex flex-col gap-1 relative sm:col-span-2">
                            <label
                                for="drug-search"
                                class="text-right font-medium text-[#2E5077] pt-2"
                                >اسم الدواء:</label
                            >
                            <input
                                id="drug-search"
                                type="text"
                                v-model="searchTermDrug"
                                @input="handleInput"
                                @focus="showAllDrugsOnFocus"
                                @blur="hideResults"
                                placeholder="ابدأ بكتابة اسم الدواء"
                                class="h-11 p-2.5 px-4 border border-[#B8D7D9] rounded-2xl text-base w-full transition duration-200 focus:border-[#4DA1A9] focus:ring-1 focus:ring-[#4DA1A9] bg-white shadow-none disabled:bg-gray-100 disabled:text-gray-500 disabled:cursor-not-allowed"
                                :disabled="selectedDrugName.length > 0"
                            />

                            <ul
                                v-if="showResults && uniqueFilteredDrugs.length"
                                class="absolute top-full left-0 right-0 z-10 list-none p-0 m-0 border border-[#4DA1A9] border-t-0 rounded-b-lg max-h-52 overflow-y-auto bg-white shadow-xl"
                            >
                                <li
                                    v-for="drug in uniqueFilteredDrugs"
                                    :key="drug.id"
                                    @mousedown="selectDrug(drug)"
                                    class="p-2.5 px-4 cursor-pointer border-b border-gray-100 text-sm text-[#2E5077] hover:bg-[#EAF3F4]"
                                >
                                    {{ drug.name }}
                                    <span
                                        v-if="drug.mostCommonDosage"
                                        class="text-gray-500 text-xs"
                                        >({{ drug.mostCommonDosage }})</span
                                    >
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1 col-span-1">
                            <label
                                :for="quantityInputId"
                                class="text-right font-medium text-[#2E5077] pt-2"
                            >
                                الكمية (<span class="unit-text">{{
                                    quantityUnit
                                }}</span
                                >):
                            </label>
                            <input
                                :id="quantityInputId"
                                type="number"
                                min="0"
                                v-model.number="dailyQuantity"
                                class="h-11 p-2.5 px-4 border border-[#B8D7D9] rounded-2xl text-base w-full transition duration-200 focus:border-[#4DA1A9] focus:ring-1 focus:ring-[#4DA1A9] bg-white shadow-none disabled:bg-gray-100 disabled:text-gray-500 disabled:cursor-not-allowed"
                                placeholder="أدخل الكمية"
                                :disabled="!selectedDrugName"
                            />
                            <p
                                v-if="quantityError"
                                class="text-xs text-red-500 mt-1 font-semibold"
                            >
                                {{ quantityError }}
                            </p>
                        </div>

                        <div class="pt-9">
                            <button
                                @click="addNewDrug"
                                :disabled="!isCurrentDrugValid"
                                class="h-11 inline-flex items-center justify-center px-[25px] border-2 border-[#4DA1A9] rounded-[30px] transition-all duration-200 ease-in text-[15px] cursor-pointer text-[#4DA1A9] bg-white hover:bg-[#EAF3F4] disabled:bg-gray-300 disabled:border-gray-300 disabled:text-gray-500 disabled:cursor-not-allowed w-full"
                            >
                                <Icon icon="tabler:plus" class="w-5 h-5 ml-1" />
                                إضافة للقائمة
                            </button>
                        </div>
                    </div>

                    <div
                        v-if="
                            !selectedDrugName &&
                            (searchTermDrug.length > 0 ||
                                selectedCategory.length > 0) &&
                            filteredDrugs.length > 0
                        "
                    >
                        <p
                            class="text-sm p-3 rounded-md border-r-4 border-[#4DA1A9] bg-[#EAF3F4] text-[#2E5077]"
                        >
                            الرجاء **اختيار** دواء من قائمة النتائج الظاهرة
                            لتحديد الكمية اليومية.
                        </p>
                    </div>
                    <div
                        v-else-if="
                            !selectedDrugName &&
                            (searchTermDrug.length > 0 ||
                                selectedCategory.length > 0) &&
                            filteredDrugs.length === 0
                        "
                    >
                        <p
                            class="text-sm p-3 rounded-md border-r-4 border-red-400 bg-red-50 text-red-700"
                        >
                            لا توجد نتائج مطابقة لاسم الدواء في الفئة المختارة.
                        </p>
                    </div>

                    <div v-if="dailyDosageList.length > 0" class="mt-8">
                        <h3
                            class="text-lg font-semibold text-[#4DA1A9] border-b border-dashed border-[#B8D7D9] pb-1 mb-4"
                        >
                            الأدوية المضافة للقائمة ({{
                                dailyDosageList.length
                            }})
                        </h3>

                        <div
                            class="p-4 border border-[#B8D7D9] rounded-md bg-[#F6F4F0] max-h-48 overflow-y-auto"
                        >
                            <ul class="list-none p-0 m-0">
                                <li
                                    v-for="(item, index) in dailyDosageList"
                                    :key="index"
                                    class="bg-white p-2.5 px-4 rounded-xl mb-2 flex justify-between items-center text-sm text-[#2E5077] font-medium border border-[#B8D7D9] shadow-sm"
                                >
                                    <span>
                                        **{{ item.name }}** -
                                        {{ item.quantity }} {{ item.unit }}
                                    </span>
                                    <span
                                        class="text-red-600 cursor-pointer text-base opacity-90 hover:opacity-70 transition duration-200"
                                        @click="removeItem(index)"
                                        >❌ حذف</span
                                    >
                                </li>
                            </ul>
                        </div>
                    </div>
                    <p v-else class="text-center text-gray-500 py-2">
                        لم يتم إضافة أي أدوية بعد.
                    </p>
                </div>
            </div>

            <div
                class="p-4 sm:pr-6 sm:pl-6 pt-2 flex justify-end gap-3 sticky bottom-0 bg-[#F6F4F0] rounded-b-xl border-t border-[#B8D7D9]"
            >
                <button
                    @click="confirmAddition"
                    :disabled="!isReadyToConfirm"
                    class="inline-flex items-center px-[11px] py-[9px] border-2 border-[#ffffff8d] h-11 w-35 rounded-[30px] transition-all duration-200 ease-in relative overflow-hiddentext-[15px] cursor-pointer text-white z-[1] bg-[#4DA1A9] hover:border hover:border-[#a8a8a8] hover:bg-[#5e8c90f9] disabled:bg-gray-300 disabled:border-gray-300 disabled:text-gray-500 disabled:cursor-not-allowed"
                >
                    تأكيد القائمة ({{
                        dailyDosageList.length + (isCurrentDrugValid ? 1 : 0)
                    }})
                </button>

                <button
                    @click="closeModal"
                    class="inline-flex h-11 items-center px-[25px] border-2 border-[#b7b9bb] rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden text-[15px] cursor-pointer text-[#374151] z-[1] bg-[#e5e7eb] hover:border-[#a8a8a8] hover:bg-[#b7b9bb]"
                >
                    إلغاء
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { Icon } from "@iconify/vue";

const props = defineProps({
    isOpen: {
        type: Boolean,
        required: true
    },
    categories: {
        type: Array,
        default: () => []
    },
    allDrugsData: {
        type: Array,
        default: () => []
    },
    drugsData: {
        type: Array,
        default: () => []
    }
});

const emit = defineEmits(['close', 'confirm', 'show-alert']);

// البيانات المحلية للنموذج
const selectedCategory = ref("");
const searchTermDrug = ref("");
const filteredDrugs = ref([]);
const selectedDrugName = ref("");
const selectedDrugType = ref("");
const dailyQuantity = ref(null);
const showResults = ref(false);
const dailyDosageList = ref([]);

// الثوابت
const MAX_PILL_QTY = 15;
const MAX_LIQUID_QTY = 30;

// Computed Properties
const uniqueFilteredDrugs = computed(() => {
    const uniqueNames = new Set();
    const uniqueDrugs = [];

    filteredDrugs.value.forEach((drug) => {
        if (!uniqueNames.has(drug.name)) {
            uniqueNames.add(drug.name);
            const commonDosage = props.allDrugsData.find(
                (d) => d.name === drug.name
            )?.dosage;
            uniqueDrugs.push({ ...drug, mostCommonDosage: commonDosage });
        }
    });
    return uniqueDrugs.slice(0, 10);
});

const quantityUnit = computed(() => {
    if (selectedDrugType.value === "Tablet") return "حبة/قرص";
    if (selectedDrugType.value === "Liquid") return "مل";
    return "وحدة";
});

const quantityInputId = computed(() => {
    return selectedDrugType.value === "Tablet"
        ? "pill-quantity-input"
        : "liquid-quantity-input";
});

const quantityError = computed(() => {
    const quantity = dailyQuantity.value;
    if (quantity === null || quantity === "") return null;

    const numericQuantity = Number(quantity);
    if (isNaN(numericQuantity)) return "الكمية يجب أن تكون رقماً.";

    if (numericQuantity <= 0) {
        return "يجب أن تكون الكمية أكبر من الصفر.";
    }

    if (selectedDrugType.value === "Tablet") {
        if (numericQuantity > MAX_PILL_QTY) {
            return `لا يمكن أن تتجاوز الكمية اليومية ${MAX_PILL_QTY} حبة/قرص.`;
        }
        if (!Number.isInteger(numericQuantity)) {
            return "يجب أن يكون عدد الحبات رقماً صحيحاً (غير عشري).";
        }
    } else if (selectedDrugType.value === "Liquid") {
        if (numericQuantity > MAX_LIQUID_QTY) {
            return `لا يمكن أن تتجاوز الكمية اليومية ${MAX_LIQUID_QTY} مل.`;
        }
    }

    return null;
});

const isCurrentDrugValid = computed(() => {
    const quantity = dailyQuantity.value;
    const isQuantityValid = quantity !== null && quantity > 0;
    const noQuantityError = quantityError.value === null;

    return (
        selectedDrugName.value.length > 0 &&
        selectedDrugType.value.length > 0 &&
        isQuantityValid &&
        noQuantityError
    );
});

const isReadyToConfirm = computed(() => {
    return dailyDosageList.value.length > 0 || isCurrentDrugValid.value;
});

// الوظائف
const clearForm = () => {
    selectedCategory.value = "";
    searchTermDrug.value = "";
    selectedDrugName.value = "";
    selectedDrugType.value = "";
    dailyQuantity.value = null;
    dailyDosageList.value = [];
    filteredDrugs.value = [];
};

const fetchDrugsData = () => {
    let results = props.allDrugsData.filter((drug) => {
        const categoryMatch =
            !selectedCategory.value ||
            drug.categoryId === selectedCategory.value;
        const searchMatch =
            !searchTermDrug.value ||
            drug.name
                .toLowerCase()
                .includes(searchTermDrug.value.toLowerCase());
        return categoryMatch && searchMatch;
    });

    filteredDrugs.value = results;
};

let debounceTimer;
const handleInput = () => {
    selectedDrugName.value = "";
    selectedDrugType.value = "";
    dailyQuantity.value = null;

    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        if (searchTermDrug.value.length > 1 || selectedCategory.value) {
            fetchDrugsData();
            showResults.value = true;
        } else {
            filteredDrugs.value = [];
            showResults.value = false;
        }
    }, 300);
};

const selectDrug = (drug) => {
    searchTermDrug.value = drug.name;
    selectedDrugName.value = drug.name;

    const fullDrugData = props.allDrugsData.find((d) => d.name === drug.name);
    const newDrugType = fullDrugData ? fullDrugData.type : "";
    selectedDrugType.value = newDrugType;

    dailyQuantity.value = null;
    showResults.value = false;
};

const hideResults = () => {
    setTimeout(() => {
        showResults.value = false;
    }, 200);
};

const showAllDrugsOnFocus = () => {
    if (!searchTermDrug.value && !selectedCategory.value) {
        filteredDrugs.value = props.allDrugsData;
        showResults.value = true;
    } else {
        handleInput();
    }
};

const addNewDrug = () => {
    if (isCurrentDrugValid.value) {
        dailyDosageList.value.push({
            name: selectedDrugName.value,
            quantity: dailyQuantity.value,
            unit: quantityUnit.value,
            type: selectedDrugType.value,
            time: new Date().toLocaleTimeString("ar-EG", {
                hour: "2-digit",
                minute: "2-digit",
            }),
        });

        emit('show-alert', `✅ تم إضافة الدواء **${selectedDrugName.value}** إلى قائمة الجرعات اليومية.`);

        searchTermDrug.value = "";
        selectedCategory.value = "";
        selectedDrugName.value = "";
        selectedDrugType.value = "";
        dailyQuantity.value = null;
    } else {
        const errorMessage =
            quantityError.value ||
            "الرجاء تحديد دواء وإدخال كمية يومية صحيحة أكبر من الصفر.";
        emit('show-alert', `❌ فشل الإضافة: ${errorMessage}`);
    }
};

const removeItem = (index) => {
    dailyDosageList.value.splice(index, 1);
    emit('show-alert', "🗑️ تم حذف الدواء من القائمة بنجاح.");
};

const confirmAddition = () => {
    if (isCurrentDrugValid.value) {
        dailyDosageList.value.push({
            name: selectedDrugName.value,
            quantity: dailyQuantity.value,
            unit: quantityUnit.value,
            type: selectedDrugType.value,
            time: new Date().toLocaleTimeString("ar-EG", {
                hour: "2-digit",
                minute: "2-digit",
            }),
        });
        searchTermDrug.value = "";
        selectedCategory.value = "";
        selectedDrugName.value = "";
        selectedDrugType.value = "";
        dailyQuantity.value = null;
    }

    if (dailyDosageList.value.length === 0) {
        emit('show-alert', "⚠️ لا يمكنك التأكيد دون إضافة دواء واحد على الأقل بكمية يومية.");
    } else {
        emit('confirm', dailyDosageList.value);
        closeModal();
    }
};

const getDrugType = (drugName) => {
    const drugInfo = props.allDrugsData.find(d => d.name.toLowerCase() === drugName.toLowerCase());
    return drugInfo ? drugInfo.type : 'Tablet';
};

const closeModal = () => {
    clearForm();
    emit('close');
};

// عند فتح النموذج، نقوم بتعبئة القائمة تلقائياً بالأدوية الناقصة
watch(() => props.isOpen, (isOpen) => {
    if (isOpen) {
        clearForm();
        
        const TARGET_QUANTITY = 100;
        const REORDER_THRESHOLD = 20;

        const drugsToReorder = props.drugsData.filter(
            (drug) => drug.quantity <= REORDER_THRESHOLD
        );

        if (drugsToReorder.length > 0) {
            const reorderList = drugsToReorder.map((drug) => {
                const quantityNeeded = TARGET_QUANTITY - drug.quantity;
                const drugType = getDrugType(drug.drugName);
                const unit = drugType === 'Liquid' ? 'مل' : 'حبة/قرص';

                return {
                    name: drug.drugName,
                    quantity: quantityNeeded > 0 ? quantityNeeded : 0,
                    unit: unit,
                    type: drugType,
                    time: new Date().toLocaleTimeString("ar-EG", {
                        hour: "2-digit",
                        minute: "2-digit",
                    }),
                };
            });

            dailyDosageList.value = reorderList;
            emit('show-alert', `💡 تم إدراج ${reorderList.length} دواء ناقص في القائمة تلقائياً.`);
        }
    }
});

// التهيئة الأولية
fetchDrugsData();
</script>