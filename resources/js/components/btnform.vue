<style scoped>
/* ---------------------------------- */
/* تنسيقات الزر الأساسية        */
/* ---------------------------------- */
.button {
    /* التغيير الأساسي: استخدام flexbox لترتيب الأيقونة والنص */
    display: inline-flex;
    align-items: center; 
    
    padding:  9px;
    padding-left: 11px;
    padding-right: 11px;
    border: 2px solid #ffffff8d;
    border-radius: 30px;
    transition: all 0.2s ease-in;
    position: relative;
    overflow: hidden;
    font-size: 15px;
    cursor: pointer;
    color: white;
    z-index: 1;
    background-color: #4DA1A9;
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
    background-color: #39bda7;
    border-radius: 50%;
    display: block;
    transition: all 0.5s 0.1s cubic-bezier(0.55, 0, 0.1, 1);
    z-index: -1;
}

.button:hover {
    color: white;
    border: 1px solid #a8a8a8;
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
    <button class="button" type="submit">
       
       تأكيد التسجيل
    </button>
</template>

<script setup>
</script>