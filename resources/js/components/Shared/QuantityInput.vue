<template>
    <div class="flex flex-col gap-2">
        <!-- حقل إدخال العلب/العبوات -->
        <div v-if="unitsPerBox && unitsPerBox > 1" class="flex items-center gap-2 bg-green-50 p-2 rounded-xl border border-green-200">
            <label :for="boxInputId" class="text-sm font-bold text-green-600 px-2 whitespace-nowrap">
                {{ unit === 'مل' ? 'العبوات' : 'العلب' }}:
            </label>
            <input
                :id="boxInputId"
                type="number"
                :value="boxes"
                @input="handleBoxesInput"
                :min="0"
                :max="maxBoxes"
                :disabled="disabled"
                class="w-20 h-10 text-center bg-white border rounded-lg focus:ring-2 focus:ring-green-500/20 outline-none transition-all font-bold text-green-700 text-lg border-green-300"
                :class="inputClasses"
            />
            <span class="text-xs text-green-600 font-medium whitespace-nowrap">×{{ unitsPerBox }}</span>
        </div>

        <!-- حقل إدخال الوحدات الأساسية -->
        <div class="flex items-center gap-2 bg-gray-50 p-2 rounded-xl border border-gray-200">
            <label :for="unitsInputId" class="text-sm font-bold text-gray-500 px-2 whitespace-nowrap">
                {{ displayLabel }}:
            </label>
            <input
                :id="unitsInputId"
                type="number"
                :value="quantity"
                @input="handleQuantityInput"
                :min="0"
                :max="maxQuantity"
                :disabled="disabled"
                class="w-20 h-10 text-center bg-white border rounded-lg focus:ring-2 focus:ring-[#4DA1A9]/20 outline-none transition-all font-bold text-[#2E5077] text-lg"
                :class="quantityInputClasses"
            />
            <span v-if="showUnit" class="text-xs text-gray-500 font-medium">{{ unit || 'حبة' }}</span>
        </div>

        <!-- رسالة تحذير إذا تجاوزت الكمية المتاحة -->
        <div v-if="quantity > maxQuantity && maxQuantity > 0" class="flex items-center gap-2 bg-red-50 border border-red-200 rounded-lg p-2">
            <Icon icon="solar:danger-circle-bold" class="w-4 h-4 text-red-500" />
            <span class="text-xs text-red-600 font-medium">
                الحد الأقصى: {{ maxQuantity }} {{ unit || 'حبة' }}
            </span>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Icon } from '@iconify/vue';

const props = defineProps({
    // الكمية بالوحدات الأساسية
    quantity: {
        type: Number,
        default: 0
    },
    // عدد الوحدات في العلبة/العبوة
    unitsPerBox: {
        type: Number,
        default: 1
    },
    // الوحدة (حبة، مل، حقنة، جرام)
    unit: {
        type: String,
        default: 'حبة'
    },
    // الحد الأقصى للكمية
    maxQuantity: {
        type: Number,
        default: Infinity
    },
    // تعطيل الحقول
    disabled: {
        type: Boolean,
        default: false
    },
    // عرض الوحدة بجانب الحقل
    showUnit: {
        type: Boolean,
        default: true
    },
    // تسمية الحقل
    label: {
        type: String,
        default: null
    },
    // معرف فريد للحقول
    uniqueId: {
        type: String,
        default: () => `qty-${Math.random().toString(36).substr(2, 9)}`
    },
    // فئات CSS إضافية للحقول
    inputClasses: {
        type: String,
        default: ''
    }
});

const emit = defineEmits(['update:quantity']);

// حساب عدد العلب من الكمية
const boxes = computed(() => {
    if (!props.unitsPerBox || props.unitsPerBox <= 1) return 0;
    return Math.floor(props.quantity / props.unitsPerBox);
});

// حساب الحد الأقصى للعلب
const maxBoxes = computed(() => {
    if (!props.unitsPerBox || props.unitsPerBox <= 1) return 0;
    return Math.floor(props.maxQuantity / props.unitsPerBox);
});

// التسمية المعروضة
const displayLabel = computed(() => {
    if (props.label) return props.label;
    if (props.unitsPerBox && props.unitsPerBox > 1) {
        return props.unit || 'حبة';
    }
    return 'الكمية';
});

// معرفات الحقول
const boxInputId = computed(() => `${props.uniqueId}-boxes`);
const unitsInputId = computed(() => `${props.uniqueId}-units`);

// فئات CSS للحقل بناءً على القيمة
const quantityInputClasses = computed(() => {
    if (props.quantity > props.maxQuantity && props.maxQuantity > 0) {
        return 'border-red-300 focus:border-red-500';
    }
    if (props.quantity > 0 && props.quantity <= props.maxQuantity) {
        return 'border-green-300 focus:border-green-500';
    }
    return 'border-gray-200 focus:border-[#4DA1A9]';
});

// معالجة تغيير عدد العلب
const handleBoxesInput = (event) => {
    const newBoxes = parseInt(event.target.value) || 0;
    const newQuantity = newBoxes * props.unitsPerBox;
    
    // التحقق من عدم تجاوز الحد الأقصى
    if (newQuantity <= props.maxQuantity) {
        emit('update:quantity', newQuantity);
    } else {
        // إذا تجاوز، استخدم الحد الأقصى
        emit('update:quantity', props.maxQuantity);
    }
};

// معالجة تغيير الكمية بالوحدات
const handleQuantityInput = (event) => {
    let newQuantity = parseInt(event.target.value) || 0;
    
    // التحقق من الحد الأدنى والأقصى
    if (newQuantity < 0) newQuantity = 0;
    if (newQuantity > props.maxQuantity && props.maxQuantity > 0) {
        newQuantity = props.maxQuantity;
    }
    
    emit('update:quantity', newQuantity);
};
</script>

<style scoped>
/* إزالة الأسهم من حقول الأرقام في Chrome و Safari */
input[type="number"]::-webkit-inner-spin-button,
input[type="number"]::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

/* إزالة الأسهم من حقول الأرقام في Firefox */
input[type="number"] {
    -moz-appearance: textfield;
    appearance: textfield;
}
</style>
