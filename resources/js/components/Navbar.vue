<template>
  <header
    class="navbar bg-white border-b border-primary/10 top-0 z-10 shadow-sm px-4 lg:px-8 py-4 flex justify-between items-center"
  >
    <div class="flex items-center">
      <label for="my-drawer" class="btn btn-ghost lg:hidden p-0 mr-4">
        <Icon icon="ic:baseline-menu" class="w-6 h-6 text-[#2E5077]" />
      </label>
    </div>

    <section class="flex gap-6">
      <div class="dropdown dropdown-end">
        <button tabindex="0" role="button" class="btn btn-ghost btn-circle avatar">
          <div class="w-10 rounded-full">
            <img src="/assets/OIP.webp" alt="user" class="h-10 w-10 object-cover" />
          </div>
        </button>
        <ul
          tabindex="0"
          class="mt-5 z-10 p-2 shadow menu menu-sm dropdown-content bg-base-100 rounded-box w-58 space-y-3 font-semibold text-base"
        >
          <li><a>الملف الشخصي</a></li>
          <li><a>الإعدادات</a></li>
          <li><a>تسجيل الخروج</a></li>
        </ul>
      </div>
    </section>
  </header>
</template>

<script setup>
import { Icon } from "@iconify/vue";
</script>
