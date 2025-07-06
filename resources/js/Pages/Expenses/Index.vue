<template>
  <AppLayout title="إدارة المصروفات">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">المصروفات</h2>
    </template>
    <div class="py-12" dir="rtl">
      <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow-md rounded-xl p-6 border">
          <!-- فلترة المصروفات -->
          <form @submit.prevent="filterExpenses" class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-6">
            <div>
              <label class="block text-gray-700 mb-1">يوم محدد</label>
              <input v-model="filtersLocal.expense_date" type="date" class="input-style" />
            </div>
            <div>
              <label class="block text-gray-700 mb-1">من تاريخ</label>
              <input v-model="filtersLocal.from" type="date" class="input-style" />
            </div>
            <div>
              <label class="block text-gray-700 mb-1">إلى تاريخ</label>
              <input v-model="filtersLocal.to" type="date" class="input-style" />
            </div>
            <div class="flex items-end">
              <button type="submit" class="btn-primary w-full">بحث</button>
            </div>
          </form>

          <h3 class="text-lg font-bold mb-4">إضافة مصروف جديد</h3>
          <form @submit.prevent="submitExpense" class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <div>
              <label class="block text-gray-700 mb-1">الوصف</label>
              <input v-model="form.description" type="text" class="input-style" required />
            </div>
            <div>
              <label class="block text-gray-700 mb-1">المبلغ</label>
              <input v-model="form.amount" type="number" step="0.01" class="input-style" required />
            </div>
            <div>
              <label class="block text-gray-700 mb-1">تاريخ المصروف</label>
              <input v-model="form.expense_date" type="date" class="input-style" required />
            </div>
            <div class="sm:col-span-3">
              <button type="submit" class="btn-primary w-full">إضافة</button>
            </div>
          </form>

          <h3 class="text-lg font-bold mb-4">قائمة المصروفات</h3>
          <div class="overflow-x-auto">
            <table class="w-full bg-white border rounded-xl shadow text-end responsive-table">
              <thead class="bg-gray-200">
                <tr>
                  <th class="p-3">الوصف</th>
                  <th class="p-3">المبلغ</th>
                  <th class="p-3">التاريخ</th>
                  <th class="p-3">الإجراءات</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="expense in expenses" :key="expense.id" class="border-t hover:bg-gray-50">
                  <td class="p-3">{{ expense.description }}</td>
                  <td class="p-3">{{ formatPrice(expense.amount) }} جنيه</td>
                  <td class="p-3">{{ expense.expense_date }}</td>
                  <td class="p-3">
                    <button @click="editExpense(expense)" class="btn-yellow text-xs">تعديل</button>
                    <button @click="deleteExpense(expense.id)" class="btn-red text-xs ml-2">حذف</button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- نافذة التعديل -->
      <div v-if="editingExpense" class="fixed inset-0 bg-black bg-opacity-40 z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl shadow-lg p-6 w-full max-w-md">
          <h3 class="text-lg font-bold mb-4">تعديل المصروف</h3>
          <form @submit.prevent="updateExpense">
            <div class="mb-3">
              <label class="block text-gray-700 mb-1">الوصف</label>
              <input v-model="editForm.description" type="text" class="input-style" required />
            </div>
            <div class="mb-3">
              <label class="block text-gray-700 mb-1">المبلغ</label>
              <input v-model="editForm.amount" type="number" step="0.01" class="input-style" required />
            </div>
            <div class="mb-3">
              <label class="block text-gray-700 mb-1">تاريخ المصروف</label>
              <input v-model="editForm.expense_date" type="date" class="input-style" required />
            </div>
            <div class="flex gap-2 mt-4">
              <button type="submit" class="btn-primary flex-1">حفظ</button>
              <button type="button" @click="editingExpense = null" class="btn-gray flex-1">إلغاء</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const page = usePage();
const props = defineProps({
  expenses: Array,
  filters: Object,
});

const filtersLocal = ref({
  expense_date: props.filters?.expense_date || '',
  from: props.filters?.from || '',
  to: props.filters?.to || '',
});

const form = ref({
  description: '',
  amount: '',
  expense_date: new Date().toISOString().slice(0, 10),
});

const editingExpense = ref(null);
const editForm = ref({ description: '', amount: '', expense_date: '' });

function filterExpenses() {
  // إذا تم اختيار يوم محدد، تجاهل الفترة
  const data = {
    expense_date: filtersLocal.value.expense_date,
    from: filtersLocal.value.expense_date ? '' : filtersLocal.value.from,
    to: filtersLocal.value.expense_date ? '' : filtersLocal.value.to,
  };
  router.get(route('expenses.index'), data, { preserveState: true, preserveScroll: true });
}

function submitExpense() {
  router.post(route('expenses.store'), form.value, {
    onSuccess: () => {
      form.value = { description: '', amount: '', expense_date: new Date().toISOString().slice(0, 10) };
    },
  });
}

function editExpense(expense) {
  editingExpense.value = expense.id;
  editForm.value = { ...expense };
}

function updateExpense() {
  router.put(route('expenses.update', editingExpense.value), editForm.value, {
    onSuccess: () => {
      editingExpense.value = null;
    },
  });
}

function deleteExpense(id) {
  if (confirm('هل أنت متأكد من حذف هذا المصروف؟')) {
    router.delete(route('expenses.destroy', id));
  }
}

function formatPrice(price) {
  return price ? Number(price).toFixed(2) : '0.00';
}
</script>

<style scoped>
.input-style {
  @apply w-full p-2 border border-gray-300 rounded-lg focus:ring focus:ring-blue-300 transition;
}
.btn-primary {
  @apply bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition shadow-md;
}
.btn-gray {
  @apply bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg transition;
}
.btn-yellow {
  @apply bg-yellow-500 hover:bg-yellow-600 text-white font-bold px-3 py-2 rounded-lg transition;
}
.btn-red {
  @apply bg-red-500 hover:bg-red-600 text-white font-bold px-3 py-2 rounded-lg transition;
}
</style> 