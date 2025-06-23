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
            <th class="p-4"></th>
            <th class="p-4">الاسم</th>
            <th class="p-4">الكمية</th>
            <th class="p-4">الأحجام والأسعار</th>
            <th class="p-4 text-center">الصورة</th>
            <th class="p-4">الفئة</th>
            <th class="p-4 text-center">الإجراءات</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="product in products" :key="product.id">
            <tr class="border-t hover:bg-gray-50">
              <td class="p-4">
                <button v-if="product.ingredients && product.ingredients.length > 0" @click="toggleIngredients(product.id)" class="text-blue-500 hover:text-blue-700">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 transition-transform" :class="{'rotate-90': isExpanded(product.id)}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                  </svg>
                </button>
              </td>
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
            <tr v-if="isExpanded(product.id)">
              <td colspan="8" class="p-4 bg-gray-50">
                <div class="p-4 border-l-4 border-blue-400">
                  <h4 class="font-bold text-lg mb-2 text-gray-700">مكونات {{ product.name }}:</h4>
                  <div v-if="product.ingredients && product.ingredients.length">
                    <template v-for="(ingredients, size) in groupIngredientsBySize(product.ingredients)" :key="size">
                      <div class="mb-3">
                        <div class="font-semibold text-blue-700 border-b pb-1 mb-2">مكونات حجم: {{ translateSize(size) }}</div>
                        <ul class="list-disc pr-5 space-y-1">
                          <li v-for="ingredient in ingredients" :key="ingredient.id" class="text-gray-600">
                            {{ ingredient.name }} - <span class="font-semibold">{{ ingredient.pivot.quantity_consumed }} {{ ingredient.unit }}</span>
                          </li>
                        </ul>
                      </div>
                    </template>
                  </div>
                </div>
              </td>
            </tr>
          </template>
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
      expandedRows: [],
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
    },
    toggleIngredients(productId) {
      const index = this.expandedRows.indexOf(productId);
      if (index > -1) {
        this.expandedRows.splice(index, 1);
      } else {
        this.expandedRows.push(productId);
      }
    },
    isExpanded(productId) {
      return this.expandedRows.includes(productId);
    },
    groupIngredientsBySize(ingredients) {
      // يجمع المكونات حسب الحجم (pivot.size)
      return ingredients.reduce((acc, ing) => {
        const size = ing.pivot.size || 'غير محدد';
        if (!acc[size]) acc[size] = [];
        acc[size].push(ing);
        return acc;
      }, {});
    },
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