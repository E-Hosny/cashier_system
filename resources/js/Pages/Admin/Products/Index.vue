<template>
  <div class="container mx-auto p-4 sm:p-6" dir="rtl">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
      <h1 class="text-3xl font-bold text-gray-800">📦 إدارة المنتجات</h1>
      <div class="flex flex-col sm:flex-row gap-2 sm:gap-4">
        <a :href="route('admin.products.cost-analysis')" class="btn-blue text-center">💰 تحليل التكلفة</a>
        <a :href="route('admin.products.export')" class="btn-green text-center">📊 تصدير Excel</a>
        <a :href="route('admin.products.create')" class="btn-primary text-center">➕ إضافة منتج جديد</a>
        <a :href="route('admin.categories.index')" class="btn-green text-center">📁 إدارة الفئات</a>
      </div>
    </div>

    <!-- 🔍 فلاتر التصفية -->
    <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
      <div class="flex flex-col md:flex-row gap-4 items-center filters-mobile">
        <div class="flex-1">
          <label class="block text-gray-700 mb-2 font-semibold">البحث بالاسم:</label>
          <input 
            v-model="searchTerm" 
            type="text" 
            placeholder="ابحث عن منتج..." 
            class="w-full p-3 border border-gray-300 rounded-lg focus:ring focus:ring-blue-300"
          />
        </div>
        <div class="flex-1">
          <label class="block text-gray-700 mb-2 font-semibold">تصفية حسب الفئة:</label>
          <select v-model="selectedCategory" @change="filterProducts" class="w-full p-3 border border-gray-300 rounded-lg focus:ring focus:ring-blue-300">
            <option value="">جميع الفئات</option>
            <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
          </select>
        </div>
        <div class="flex gap-2">
          <button @click="clearFilters" class="btn-gray">🗑️ مسح الفلاتر</button>
          <div class="text-gray-600 text-sm flex items-center">
            <span class="font-semibold">عدد المنتجات:</span>
            <span class="mr-2 text-blue-600 font-bold">{{ products.length }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ✅ جدول عرض المنتجات -->
    <div class="overflow-x-auto bg-white rounded-xl shadow-lg">
      <!-- رسالة عدم وجود نتائج -->
      <div v-if="products.length === 0" class="p-8 text-center">
        <div class="text-gray-500 text-lg mb-4">🔍</div>
        <h3 class="text-xl font-semibold text-gray-700 mb-2">لا توجد منتجات</h3>
        <p class="text-gray-600 mb-4">
          {{ searchTerm || selectedCategory ? 'لم يتم العثور على منتجات تطابق معايير البحث المحددة.' : 'لا توجد منتجات في النظام حالياً.' }}
        </p>
        <button @click="clearFilters" class="btn-primary">عرض جميع المنتجات</button>
      </div>

      <table v-else class="w-full text-end">
        <thead class="bg-gray-200 hidden sm:table-header-group">
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
            <tr class="block sm:table-row border-t sm:border-t-0 border-gray-200 hover:bg-gray-50">
              <td class="p-4 sm:table-cell">
                <button v-if="product.ingredients && product.ingredients.length > 0" @click="toggleIngredients(product.id)" class="text-blue-500 hover:text-blue-700">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 transition-transform" :class="{'rotate-90': isExpanded(product.id)}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                  </svg>
                </button>
              </td>
              <td class="p-4 block sm:table-cell" data-label="الاسم">{{ product.name }}</td>
              <td class="p-4 block sm:table-cell" data-label="الكمية">{{ product.quantity || 'غير محدد' }}</td>
              <td class="p-4 block sm:table-cell" data-label="الأحجام والأسعار">
                <ul class="space-y-1">
                  <li v-for="variant in product.size_variants" :key="variant.size" class="text-sm">
                    <span class="font-semibold">{{ translateSize(variant.size) }}:</span>
                    <span class="text-green-700 font-bold">{{ variant.price }}</span>
                  </li>
                </ul>
              </td>
              <td class="p-4 block sm:table-cell text-center" data-label="الصورة">
                <img v-if="product.image" :src="`/storage/${product.image}`" class="h-16 w-16 object-cover rounded-md shadow-md mx-auto sm:mx-0">
              </td>
              <td class="p-4 block sm:table-cell" data-label="الفئة">{{ product.category?.name || "بدون فئة" }}</td>
              <td class="p-4 block sm:table-cell" data-label="الإجراءات">
                <div class="flex justify-center items-center gap-2">
                  <a :href="route('admin.products.edit', product.id)" class="btn-yellow">✏️ تعديل</a>
                  <button
                    v-if="$page.props.auth.user.roles && $page.props.auth.user.roles.includes('admin')"
                    @click="deleteProduct(product.id)"
                    class="btn-red"
                  >
                    🗑️ حذف
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="isExpanded(product.id)" class="block sm:table-row">
              <td colspan="8" class="p-4 bg-gray-50 block sm:table-cell">
                <div class="p-4 border-l-4 border-blue-400">
                  <h4 class="font-bold text-lg mb-2 text-gray-700">مكونات {{ product.name }}:</h4>
                  <div v-if="product.ingredients && product.ingredients.length">
                    <template v-for="(ingredients, size) in groupIngredientsBySize(product.ingredients)" :key="size">
                      <div class="mb-3">
                        <div class="font-semibold text-blue-700 border-b pb-1 mb-2">مكونات حجم: {{ translateSize(size) }}</div>
                        <ul class="list-disc pr-5 space-y-1">
                          <li v-for="ingredient in ingredients" :key="ingredient.id" class="text-gray-600">
                            {{ ingredient.name }} -
                            <span class="font-semibold">
                              {{ ingredient.pivot.quantity_consumed }}
                              {{ ingredient.consume_unit || ingredient.pivot.unit || ingredient.unit || '' }}
                            </span>
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
    categories: Array,
    filters: Object,
  },
  data() {
    return {
      sizeTranslations: {
        small: 'صغير',
        medium: 'وسط',
        large: 'كبير',
      },
      expandedRows: [],
      selectedCategory: this.filters?.category_id || '',
      searchTerm: this.filters?.searchTerm || '',
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
    filterProducts() {
      // إرسال الفلاتر إلى السيرفر
      Inertia.get(route('admin.products.index'), {
        category_id: this.selectedCategory,
        searchTerm: this.searchTerm,
      }, {
        preserveState: true,
        replace: true,
        preserveScroll: true,
      });
    },
    clearFilters() {
      this.selectedCategory = '';
      this.searchTerm = '';
      this.filterProducts();
    },
  },
  watch: {
    selectedCategory() {
      this.filterProducts();
    },
    searchTerm() {
      // بحث مباشر عند الكتابة
      this.filterProducts();
    }
  }
};
</script>

<style scoped>
.btn-primary {
  @apply bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition shadow-md;
}
.btn-blue {
  @apply bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-lg transition shadow-md;
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
.btn-gray {
  @apply bg-gray-500 hover:bg-gray-600 text-white font-bold px-4 py-2 rounded-lg transition;
}

/* تحسين الفلاتر للجوال */
@media (max-width: 640px) {
  .filters-mobile {
    flex-direction: column !important;
    align-items: stretch !important;
    gap: 1rem !important;
  }
  .filters-mobile > * {
    width: 100% !important;
  }
  .filters-mobile .btn-gray, .filters-mobile .btn-primary {
    width: 100%;
    margin-top: 0.5rem;
  }
}

/* تحسين عرض المنتجات ككروت في الجوال */
@media (max-width: 640px) {
  table {
    display: block;
    width: 100%;
  }
  thead {
    display: none;
  }
  tbody {
    display: block;
    width: 100%;
  }
  tr {
    display: block;
    background: #fff;
    margin-bottom: 1.5rem;
    border-radius: 1rem;
    box-shadow: 0 2px 8px #0001;
    padding: 1rem 0.5rem;
  }
  td {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0.75rem;
    border: none;
    border-bottom: 1px solid #f3f4f6;
    font-size: 1rem;
  }
  td[data-label]::before {
    content: attr(data-label) " :";
    font-weight: bold;
    color: #374151;
    min-width: 90px;
    margin-left: 0.5rem;
    text-align: right;
  }
  td:last-child {
    border-bottom: none;
  }
  td > img {
    margin: 0 auto;
    display: block;
    max-width: 60px;
    max-height: 60px;
    border-radius: 0.5rem;
  }
  .flex.justify-center.items-center.gap-2 {
    flex-direction: column;
    gap: 0.5rem;
  }
  .btn-yellow, .btn-red {
    width: 100%;
    font-size: 1rem;
    padding: 0.75rem 0;
  }
}
</style>