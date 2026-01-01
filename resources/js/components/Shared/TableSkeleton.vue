<template>
    <div class="animate-pulse space-y-4 w-full">
        <!-- Header Skeleton -->
        <!-- <div class="h-12 bg-gray-200 rounded-xl w-full mb-6"></div> -->
        
        <!-- Rows Skeleton -->
        <div v-for="i in rows" :key="i" class="flex gap-4">
            <div class="h-12 bg-gray-100 rounded-lg w-1/4"></div>
            <div class="h-12 bg-gray-100 rounded-lg w-1/4"></div>
            <div class="h-12 bg-gray-100 rounded-lg w-1/4"></div>
            <div class="h-12 bg-gray-100 rounded-lg w-1/4"></div>
        </div>
    </div>
</template>

<script setup>
defineProps({
    rows: {
        type: Number,
        default: 5
    }
});
</script>
