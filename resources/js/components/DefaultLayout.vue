<script setup>
import Navbar from "@/components/Navbar.vue";
import Sidebar from "@/components/Sidebar.vue";

// هذا المكون لا يحتاج منطق كبير، فقط لتجميع الأجزاء المشتركة
</script>

<template>
    <div class="drawer lg:drawer-open" dir="rtl">
        <input id="my-drawer" type="checkbox" class="drawer-toggle" checked />

        <div class="drawer-content flex flex-col bg-gray-50 min-h-screen">
            <Navbar />

            <slot></slot>
        </div>

        <Sidebar />
    </div>
</template>

<style scoped>
/* لا حاجة لـ Style هنا إلا إذا كان خاصًا بالتخطيط نفسه */
</style>