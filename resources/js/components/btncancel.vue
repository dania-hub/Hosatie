<style scoped>
/* ---------------------------------- */
/* class="bg-gray-200
 text-gray-700 hover:bg-gray-300 border-gray-300 rounded-2xl w-24 shadow-sm"/
/* ---------------------------------- */
.button {
    /* التغيير الأساسي: استخدام flexbox لترتيب الأيقونة والنص */
    display: inline-flex;
    align-items: center; 
     padding-left: 25px;
    padding-right: 25px;
    
    border: 2px solid #b7b9bb;
    border-radius: 30px;
    transition: all 0.2s ease-in;
    position: relative;
    overflow: hidden;
    font-size: 15px;
    cursor: pointer;
    color: #374151;
    z-index: 1;
    background-color: #e5e7eb
;
}


.icon-wrapper {
    
    margin-left: 8px; 
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
    background-color: #b7b9bb;
    border-radius: 50%;
    display: block;
    transition: all 0.5s 0.1s cubic-bezier(0.55, 0, 0.1, 1);
    z-index: -1;
}

.button:hover {
    color: #374151;
    border: 1px solid #a8a8a8;
}

.button:hover:before {
    top: -35%;
    background-color: #2E5077;
    transform: translateX(-50%) scaleY(1.3) scaleX(0.8);
}

.button:hover:after {
    top: -45%;
    background-color: #b7b9bb;
    transform: translateX(-50%) scaleY(1.3) scaleX(0.8);
}
</style>

<template>
    <button class="button"  
                            variant="outline"
                            type="button" @click="$emit('cancel')">
       
      إلغاء
    </button>  
</template>

<script setup>
// إضافة emit لتحديد الحدث الذي سيتم إصداره
const emit = defineEmits(['cancel']);
</script>