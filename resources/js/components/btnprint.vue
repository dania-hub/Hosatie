<style scoped>


.button {

    /* التغيير الأساسي: استخدام flexbox لترتيب الأيقونة والنص */

    display: inline-flex;

    align-items: center; 

    

    padding:  8px;

    border: 2px solid #4da1a9;

    border-radius: 30px;

    transition: all 0.2s ease-in;

    position: relative;

    overflow: hidden;

    font-size: 15px;

    cursor: pointer;

    color: #4da1a9;

    z-index: 1;

    background-color: white;

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

    color: #4da1a9;

    border: 2px solid #4da1a9;

}



.button:hover:before {

    top: -35%;

    background-color: #2E5077;

    transform: translateX(-50%) scaleY(1.3) scaleX(0.8);

}



.button:hover:after {

    top: -45%;

    background-color: #d7e9ea;

    transform: translateX(-50%) scaleY(1.3) scaleX(0.8);

}

</style>



<template>

    <button class="button">

        <span class="icon-wrapper">

            <Icon icon="mdi-light:printer" class="w-5 h-5"

            style="color: #4da1a9" />

        </span>

        طباعة

    </button>

</template>



<script setup>

import { Icon } from "@iconify/vue";

</script>