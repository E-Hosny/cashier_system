<template>
  <div class="container mx-auto p-4 sm:p-6" dir="rtl">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8 gap-4">
      <h1 class="text-3xl font-bold text-gray-800">📁 فئات المواد الخام</h1>
      <a :href="route('admin.raw-materials.index')" class="btn-gray">➡️ العودة إلى المواد الخام</a>
    </div>

    <div class="bg-white shadow-md rounded-xl p-6 border">
      <h2 class="text-xl font-semibold text-gray-700 mb-4">
        {{ editingCategory ? '🔄 تعديل الفئة' : '➕ إضافة فئة جديدة' }}
      </h2>
      <form @submit.prevent="submitCategory" class="flex flex-col sm:flex-row gap-4 mb-6">
        <input v-model="form.name" type="text" class="input-style flex-1" placeholder="اسم فئة المواد الخام" required />
        <button type="submit" class="btn-green">
          {{ editingCategory ? '💾 حفظ التعديل' : '➕ إضافة' }}
        </button>
        <button v-if="editingCategory" @click="cancelEdit" type="button" class="btn-gray">
          إلغاء
        </button>
      </form>

      <hr class="my-6">

      <h2 class="text-xl font-semibold text-gray-700 mb-4">قائمة الفئات</h2>
      <ul class="divide-y divide-gray-200">
        <li v-for="cat in categoriesList" :key="cat.id" class="flex flex-col sm:flex-row justify-between items-start sm:items-center py-3 px-2 hover:bg-gray-50 gap-2">
          <span class="text-gray-800 font-medium">{{ cat.name }}</span>
          <div class="flex gap-2 self-end sm:self-center">
            <button @click="editCategory(cat)" class="btn-yellow text-sm">تعديل</button>
            <button @click="deleteCategory(cat.id)" class="btn-red text-sm">حذف</button>
          </div>
        </li>
      </ul>
    </div>
  </div>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Inertia } from '@inertiajs/inertia';

export default {
  layout: AppLayout,
  props: {
    categories: Array,
  },
  data() {
    return {
      categoriesList: [...this.categories],
      form: {
        name: '',
      },
      editingCategory: null,
    };
  },
  methods: {
    submitCategory() {
      const url = this.editingCategory
        ? route('admin.raw-material-categories.update', this.editingCategory.id)
        : route('admin.raw-material-categories.store');

      const method = this.editingCategory ? 'put' : 'post';

      Inertia.visit(url, {
        method: method,
        data: this.form,
        onSuccess: () => {
          this.resetForm();
          Inertia.reload({ only: ['categories'] });
        },
        onError: (errors) => {
          console.error('Error:', errors);
        },
      });
    },
    editCategory(cat) {
      this.form.name = cat.name;
      this.editingCategory = cat;
    },
    cancelEdit() {
      this.resetForm();
    },
    resetForm() {
      this.form.name = '';
      this.editingCategory = null;
    },
    deleteCategory(id) {
      if (confirm('هل أنت متأكد من حذف هذه الفئة؟ قد تُزال الفئة عن المواد المرتبطة بها.')) {
        Inertia.delete(route('admin.raw-material-categories.destroy', id), {
          onSuccess: () => {
            Inertia.reload({ only: ['categories'] });
          },
        });
      }
    },
  },
  watch: {
    categories(newCategories) {
      this.categoriesList = [...newCategories];
    },
  },
};
</script>

<style scoped>
.input-style {
  @apply w-full p-3 border border-gray-300 rounded-lg focus:ring focus:ring-blue-300 transition;
}
.btn-green {
  @apply bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg transition;
}
.btn-gray {
  @apply bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg transition;
}
.btn-yellow {
  @apply bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-1 px-3 rounded-lg transition;
}
.btn-red {
  @apply bg-red-500 hover:bg-red-600 text-white font-bold py-1 px-3 rounded-lg transition;
}
</style>
