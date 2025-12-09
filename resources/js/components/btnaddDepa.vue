<style scoped>
/* ---------------------------------- */
/* تنسيقات الزر الأساسية (Mobile-First)  */
/* ---------------------------------- */
.button {
    /* أساسيات التصميم */
    display: inline-flex;
    align-items: center; 
    justify-content: center; /* للمحاذاة المركزية */
    transition: all 0.2s ease-in;
    position: relative;
    overflow: hidden;
    cursor: pointer;
    z-index: 1;
    width: 62%;

    /* تنسيقات اللون والحدود */
    color: white;
    background-color: #4DA1A9;
    border: 2px solid #ffffff8d;
    border-radius: 30px;

    /* التجاوب على الهواتف الصغيرة (Default) */
    padding: 8px 14px; /* تصميم أكثر اكتنازًا للهواتف */
    font-size: 13px; /* خط أصغر ليتسع على شاشات الهاتف */
}


/* التجاوب للشاشات الأكبر (Tablets and Desktops) */
@media (min-width: 620px) {
    .button {
        padding: 10px 10px; /* تباعد أكبر للكمبيوتر المكتبي */
        font-size: 16px; /* خط أكبر وأوضح للشاشات الواسعة */
    }
}


.icon-wrapper {
    /* ضبط الهامش ليتوافق مع RTL: الأيقونة قبل النص، لذا الهامش على اليمين */
    margin-right: 1px; 
    margin-left: 0; 
    display: flex; 
}

.button:before {
    content: "";
    position: absolute;
    left: 50%;
    transform: translateX(-50%) scaleY(1) scaleX(1.25);
    top: 100%;
    width: 140%;
    height: 180%;
    background-color: rgba(0, 0, 0, 0.05);
    border-radius: 50%;
    display: block;
    transition: all 0.5s 0.1s cubic-bezier(0.55, 0, 0.1, 1);
    z-index: -1;
}

.button:after {
    content: "";
    position: absolute;
    left: 55%;
    transform: translateX(-50%) scaleY(1) scaleX(1.45);
    top: 180%;
    width: 160%;
    height: 190%;
    background-color: #39bda7;
    border-radius: 50%;
    display: block;
    transition: all 0.5s 0.1s cubic-bezier(0.55, 0, 0.1, 1);
    z-index: -1;
}

.button:hover {
    color: white;
    border: 2px solid #a8a8a8;
}

.button:hover:before {
    top: -35%;
    background-color: #2E5077;
    transform: translateX(-50%) scaleY(1.3) scaleX(0.8);
}

.button:hover:after {
    top: -45%;
    background-color: #5e8c90f9;
    transform: translateX(-50%) scaleY(1.3) scaleX(0.8);
}
</style>

<template>
    <button class="button " @click="handleClick">
        <span class="icon-wrapper">
            <Icon icon="line-md:person-add" class="w-5 h-5" />
        </span>
        إضافة قسم 
    </button>
</template>

<script setup>
import { Icon } from "@iconify/vue";

const emit = defineEmits(["openModal"]);

const handleClick = () => {
    emit("openModal");
};
</script>