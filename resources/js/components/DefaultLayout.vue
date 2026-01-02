<script setup>
import Navbar from "@/components/Navbar.vue";
import Sidebar from "@/components/Sidebar.vue";

// هذا المكون لا يحتاج منطق كبير، فقط لتجميع الأجزاء المشتركة
</script>

<template>
    <div class="drawer lg:drawer-open h-screen overflow-hidden" dir="rtl">
        <input id="my-drawer" type="checkbox" class="drawer-toggle" checked />

        <div class="drawer-content flex flex-col bg-white h-full overflow-hidden">
            <Navbar class="flex-shrink-0" />

            <div class="flex-grow overflow-y-auto bg-gray-50">
                <slot></slot>
            </div>
        </div>

        <div class="drawer-side h-full overflow-hidden">
            <label for="my-drawer" aria-label="close sidebar" class="drawer-overlay"></label>
            <Sidebar />
        </div>
    </div>
</template>

<style scoped>
/* لا حاجة لـ Style هنا إلا إذا كان خاصًا بالتخطيط نفسه */
</style>