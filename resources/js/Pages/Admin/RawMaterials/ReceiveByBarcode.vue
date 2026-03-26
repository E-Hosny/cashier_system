<template>
  <div class="container mx-auto p-6" dir="rtl">
    <div class="flex justify-between items-center mb-8">
      <h1 class="text-3xl font-bold text-gray-800">📥 سحب المنتجات (استلام بالباركود)</h1>
      <a :href="route('admin.raw-materials.index')" class="btn-gray">⬅️ العودة للقائمة</a>
    </div>

    <div class="bg-white shadow-md rounded-xl p-6 border max-w-xl">
      <p class="text-gray-600 mb-6">
        امسح الباركود أو انسخ الرمز الظاهر على الملصق. عند التأكيد تُضاف الكمية إلى مخزون المادة الخام.
      </p>

      <form @submit.prevent="submit" class="space-y-4">
        <div>
          <label for="label_code" class="block text-gray-700 font-medium mb-2">رمز الملصق</label>
          <input
            id="label_code"
            v-model="form.label_code"
            type="text"
            class="input-style font-mono"
            placeholder="الصق أو امسح الكود"
            autocomplete="off"
            required
          />
          <p v-if="form.errors.label_code" class="text-red-600 text-sm mt-1">{{ form.errors.label_code }}</p>
        </div>
        <button type="submit" class="btn-primary w-full" :disabled="form.processing">
          ✓ تأكيد الاستلام وإضافة للمخزون
        </button>
      </form>
    </div>
  </div>
</template>

<script>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

export default {
  layout: AppLayout,
  setup() {
    const form = useForm({
      label_code: '',
    });

    function submit() {
      form.post(route('admin.raw-materials.pending-receive.store'));
    }

    return { form, submit };
  },
};
</script>

<style scoped>
.input-style {
  @apply w-full p-3 border border-gray-300 rounded-lg focus:ring focus:ring-blue-300;
}
.btn-primary {
  @apply bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-4 rounded-lg transition disabled:opacity-50;
}
.btn-gray {
  @apply bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg transition;
}
</style>
