<template>
  <div class="pagination">
    <button class="outline" :disabled="page <= 1 || disabled" @click="$emit('page', page - 1)">Назад</button>
    <span>Страница {{ page }} из {{ Math.max(1, pages) }} · Записей: {{ total }}</span>
    <button class="outline" :disabled="page >= pages || disabled" @click="$emit('page', page + 1)">Вперёд</button>
    <label>Строк: <select :value="perPage" :disabled="disabled" @change="$emit('size', Number($event.target.value))"><option v-for="size in [15, 50, 100]" :key="size" :value="size">{{ size }}</option></select></label>
  </div>
</template>
<script>
export default { props: { page: Number, pages: Number, total: Number, perPage: Number, disabled: Boolean } };
</script>
