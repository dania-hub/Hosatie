<style scoped>
/* ---------------------------------- */
/* التنسيقات المتجاوبة (Mobile-First)  */
/* ---------------------------------- */

.container {
    /* 1. التجاوب في العرض: تعيين العرض ليناسب الجهاز الأب */
    position: relative; 
    width: 100%; 
    max-width: 500px; 
}

.input {
    /* أساسيات التصميم */
    border: 1px solid #4da1a9;
    width: 100%; 
    
    /* التجاوب في الأبعاد (افتراضي للهواتف الصغيرة) */
    height: 2.5rem; 
    font-size: 0.9rem; 
    padding-left: 0.8rem;
    padding-right: 2.5rem; /* مسافة كافية للأيقونة */
    
    /* الخصائص الأخرى */
    outline: none;
    overflow: hidden;
    background-color: #f3f3f3;
    border-radius: 20px;
    transition: all 0.3s ease; 
}
.input::placeholder {
    font-size: 0.85rem;
    color: #888;
}

/* 2. تحسين المظهر على الشاشات الأكبر (Tablet & Desktop) */
@media (min-width: 640px) {
    .input {
        height: 2.8rem; 
        font-size: 1rem; 
        padding-right: 3rem; 
    }
}

.input:hover,
.input:focus {
    border: 2px solid #4da1a9;
    /* تحسين الظل ليكون أكثر احترافية وأقل حدة */
    box-shadow: 0px 0px 0px 4px rgba(77, 161, 169, 0.2); 
    background-color: white;
}

/* نمط الأيقونة الحاوية */
.icon-wrapper {
    position: absolute;
    right: 0;
    top: 0;
    bottom: 0;
    display: flex;
    align-items: center;
    
    padding-right: 0.75rem; 
    pointer-events: none; 
}
</style>

<template>
    <div class="container">
        <input 
            class="input" 
            :placeholder="placeholder" 
            dir="rtl" 
            :value="modelValue" 
            @input="$emit('update:modelValue', $event.target.value)" 
        />
        
        <span class="icon-wrapper">
            <Icon icon="mdi:magnify" class="w-5 h-5" style="color: #4da1a9" />
        </span>
    </div>
</template>

<script setup>
import { Icon } from "@iconify/vue";
import { defineProps, defineEmits } from "vue"; 

const props = defineProps({
    modelValue: {
        type: String,
        default: "",
    },
    placeholder: {
        type: String,
        default: "ابحث....",
    },
});

const emit = defineEmits(["update:modelValue"]);
</script>