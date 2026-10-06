<template>
  <form class="card" @submit.prevent="$emit('save', draft)">
    <h3>{{ record.id ? 'Редактирование' : 'Новая запись' }}</h3>
    <div class="edit-grid"><div v-for="field in fields" :key="field.field" class="edit-field">
      <label :for="'edit-' + field.field">{{ field.label }}</label>
      <select v-if="field.type === 'select'" :id="'edit-' + field.field" v-model="draft[field.field]"><option v-for="option in field.options" :key="option.value" :value="option.value">{{ option.label }}</option></select>
      <input v-else :id="'edit-' + field.field" v-model="draft[field.field]" :type="field.type || 'text'" :step="field.type === 'time' ? 1 : undefined" :min="field.type === 'number' ? 0 : undefined" :autocomplete="field.type === 'password' ? 'new-password' : undefined">
    </div></div>
    <p v-if="error" role="alert" class="alert alert-danger">{{ error }}</p>
    <div class="edit-actions"><button class="primary" :disabled="busy">Сохранить</button><button type="button" class="outline" :disabled="busy" @click="$emit('cancel')">Отмена</button></div>
  </form>
</template>
<script>
export default {
  props: { fields: Array, record: Object, busy: Boolean, error: String },
  data() {
    // Редактор работает с копией: отмена не изменяет строку в хранилище.
    const draft = Object.fromEntries(this.fields.map(field => [field.field, field.type === 'checkbox' ? Boolean(this.record[field.field] ?? (field.field === 'is_occupied')) : this.record[field.field] ?? '']));
    if (this.record.id) draft.id = this.record.id;
    return { draft };
  },
};
</script>
