<template>
  <div class="container mx-auto p-6" dir="rtl">
    <div class="flex justify-between items-center mb-8">
      <h1 class="text-3xl font-bold text-gray-800">📦 إدارة المنتجات</h1>
      <div class="flex gap-4">
        <a :href="route('admin.products.create')" class="btn-primary">➕ إضافة منتج جديد</a>
        <a :href="route('admin.categories.index')" class="btn-green">📁 إدارة الفئات</a>
      </div>
    </div>

    <!-- ✅ جدول عرض المنتجات -->
    <div class="overflow-x-auto">
      <table class="w-full bg-white border rounded-xl shadow-lg text-end">
        <thead class="bg-gray-200">
          <tr>
            <th class="p-4">الاسم</th>
            <th class="p-4">الكمية</th>
            <th class="p-4">الأحجام والأسعار</th>
            <th class="p-4 text-center">الصورة</th>
            <th class="p-4">الفئة</th>
            <th class="p-4 text-center">الإجراءات</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="product in products" :key="product.id" class="border-t hover:bg-gray-50">
            <td class="p-4">{{ product.name }}</td>
            <td class="p-4">{{ product.quantity || 'غير محدد' }}</td>
            <td class="p-4">
                <ul class="space-y-1">
                    <li v-for="variant in product.size_variants" :key="variant.size" class="text-sm">
                        <span class="font-semibold">{{ translateSize(variant.size) }}:</span>
                        <span class="text-green-700 font-bold">{{ variant.price }}</span>
                    </li>
                </ul>
            </td>
            <td class="p-4 text-center">
              <img v-if="product.image" :src="`/storage/${product.image}`" class="h-16 w-16 object-cover rounded-md shadow-md inline-block">
            </td>
            <td class="p-4">{{ product.category?.name || "بدون فئة" }}</td>
            <td class="p-4 text-center">
              <div class="flex justify-center items-center gap-2">
                <a :href="route('admin.products.edit', product.id)" class="btn-yellow">✏️ تعديل</a>
                <button @click="deleteProduct(product.id)" class="btn-red">🗑️ حذف</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script>
import { Inertia } from "@inertiajs/inertia";
import AppLayout from '@/Layouts/AppLayout.vue';

export default {
  layout: AppLayout,
  props: {
    products: Array,
  },
  data() {
    return {
      sizeTranslations: {
        small: 'صغير',
        medium: 'وسط',
        large: 'كبير',
      },
    };
  },
  methods: {
    deleteProduct(id) {
      if (confirm("هل أنت متأكد من حذف هذا المنتج؟")) {
        Inertia.delete(route("admin.products.destroy", id));
      }
    },
    translateSize(size) {
        return this.sizeTranslations[size] || size;
    }
  },
};
</script>

<style scoped>
.btn-primary {
  @apply bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition shadow-md;
}
.btn-green {
  @apply bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg transition shadow-md;
}
.btn-yellow {
  @apply bg-yellow-500 hover:bg-yellow-600 text-white font-bold px-4 py-2 rounded-lg transition;
}
.btn-red {
  @apply bg-red-500 hover:bg-red-600 text-white font-bold px-4 py-2 rounded-lg transition;
}
</style>