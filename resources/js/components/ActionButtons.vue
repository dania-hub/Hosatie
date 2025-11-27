<template>
  <div class="buttons-wrapper">
    <button class="filter-button" @click="$emit('toggle-filter')">
      <i class="fas fa-filter"></i> فلترة حسب
    </button>
    <button class="add-button" @click="$emit('add-new')">
      <i class="fas fa-plus"></i> إضافة حساب جديد
    </button>
  </div>
</template>

<script>
export default {
  name: "ActionButtons",
};
</script>

<style scoped>
.buttons-wrapper {
  margin: 15px 0;
  display: flex;
  gap: 15px;
}
button {
  background-color: #d1e5e5;
  border: none;
  border-radius: 6px;
  padding: 6px 14px;
  cursor: pointer;
  font-weight: bold;
  color: #2f4858;
  display: flex;
  align-items: center;
  gap: 5px;
  transition: background-color 0.3s ease;
}
button:hover {
  background-color: #b3c8c8;
  color: #17252a;
}
.filter-button i, .add-button i {
  font-size: 14px;
}
</style>