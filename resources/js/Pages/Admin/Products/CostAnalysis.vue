<template>
  <div class="container mx-auto p-4 sm:p-6" dir="rtl">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8 gap-4">
      <h1 class="text-3xl font-bold text-gray-800">💰 تحليل تكلفة المنتجات</h1>
      <a :href="route('admin.products.index')" class="btn-gray">➡️ العودة إلى المنتجات</a>
    </div>

    <div class="bg-white shadow-lg rounded-xl overflow-x-auto">
      <table class="w-full text-end">
        <thead class="bg-gray-200 hidden sm:table-header-group">
          <tr>
            <th class="p-4">اسم المنتج</th>
            <th class="p-4">الحجم</th>
            <th class="p-4">سعر البيع</th>
            <th class="p-4">تكلفة المواد الخام</th>
            <th class="p-4">هامش الربح</th>
            <th class="p-4">نسبة الربح</th>
            <th class="p-4">تفاصيل المكونات</th>
            <th class="p-4 text-center">التفاصيل</th>
          </tr>
        </thead>
        <tbody>
          <template v-if="products && products.length > 0">
            <template v-for="product in products" :key="product.id">
              <tr v-for="variant in (Array.isArray(product.size_variants) ? product.size_variants.filter(v => v && v.size) : [])" :key="`${product.id}-${variant.size}`" class="block sm:table-row border-t sm:border-t-0 border-gray-200 hover:bg-gray-50">
                <td class="p-4 block sm:table-cell" data-label="اسم المنتج">{{ product.name }}</td>
                <td class="p-4 block sm:table-cell" data-label="الحجم">{{ variant && variant.size ? translateSize(variant.size) : '' }}</td>
                <td class="p-4 block sm:table-cell font-bold text-green-700" data-label="سعر البيع">
                  {{ variant && variant.price ? variant.price : '' }} جنيه
                  <span v-if="getIngredientsForSize(product, variant.size).length === 0" title="بدون مكونات">🚫</span>
                </td>
                <td class="p-4 block sm:table-cell font-bold text-red-700" data-label="تكلفة المواد الخام">
                  <span v-if="getIngredientsForSize(product, variant.size).length === 0">-</span>
                  <span v-else>{{ getCostPrice(product, variant.size) }} جنيه</span>
                </td>
                <td class="p-4 block sm:table-cell font-bold" :class="variant && variant.size ? getProfitClass(product, variant.size) : ''" data-label="هامش الربح">
                  <span v-if="getIngredientsForSize(product, variant.size).length === 0">-</span>
                  <span v-else>{{ getProfitAmount(product, variant.size) }} جنيه</span>
                </td>
                <td class="p-4 block sm:table-cell font-bold" :class="variant && variant.size ? getProfitClass(product, variant.size) : ''" data-label="نسبة الربح">
                  <span v-if="getIngredientsForSize(product, variant.size).length === 0">-</span>
                  <span v-else>{{ getProfitMargin(product, variant.size) }}%</span>
                </td>
                <td class="p-4 block sm:table-cell" data-label="تفاصيل المكونات">
                  <template v-if="getIngredientsForSize(product, variant.size).length > 0">
                    <ul class="list-disc list-inside space-y-1">
                      <li v-for="ingredient in getIngredientsForSize(product, variant.size)" :key="ingredient.id">
                        <span class="font-semibold">{{ ingredient.name }}</span>:
                        <span>{{ ingredient.pivot.quantity_consumed }}</span>
                        <span>{{ ingredient.pivot.unit || ingredient.consume_unit || ingredient.unit }}</span>
                        <span v-if="calculateIngredientCost(ingredient) && typeof calculateIngredientCost(ingredient) !== 'string'">
                          ({{ calculateIngredientCost(ingredient) }} جنيه)
                        </span>
                        <span v-else class="text-red-600">⚠️</span>
                      </li>
                    </ul>
                  </template>
                  <template v-else>
                    <span class="text-gray-400">🚫 بدون مكونات</span>
                  </template>
                </td>
                <td class="p-4 block sm:table-cell" data-label="التفاصيل">
                  <button @click="variant && variant.size && toggleDetails(`${product.id}-${variant.size}`)" class="btn-blue text-sm">
                    {{ variant && variant.size && isExpanded(`${product.id}-${variant.size}`) ? 'إخفاء' : 'عرض' }} التفاصيل
                  </button>
                </td>
              </tr>
              <tr v-if="variant && variant.size && isExpanded(`${product.id}-${variant.size}`)" class="block sm:table-row">
                <td colspan="7" class="p-4 bg-gray-50 block sm:table-cell">
                  <div class="p-4 border-l-4 border-blue-400">
                    <h4 class="font-bold text-lg mb-3 text-gray-700">تفاصيل تكلفة {{ product.name }} - {{ variant && variant.size ? translateSize(variant.size) : '' }}:</h4>
                    <div v-if="variant && variant.size && getIngredientsForSize(product, variant.size).length > 0">
                      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div v-for="ingredient in getIngredientsForSize(product, variant.size)" :key="ingredient.id" class="bg-white p-3 rounded-lg border">
                          <div class="font-semibold text-blue-700">{{ ingredient.name }}</div>
                          <div class="text-sm text-gray-600">
                            الكمية: {{ ingredient.pivot.quantity_consumed }} {{ ingredient.pivot.unit || ingredient.consume_unit || ingredient.unit }}
                          </div>
                          <div class="text-sm text-gray-600">
                            سعر وحدة الاستهلاك: {{ ingredient.unit_consume_price || 'غير محدد' }} جنيه / {{ ingredient.consume_unit || ingredient.unit }}
                          </div>
                          <div :class="['font-bold', (calculateIngredientCost(ingredient) === '⚠️ لم يتم تحديد سعر وحدة الاستهلاك') ? 'text-red-600' : 'text-green-700']">
                            التكلفة: {{ calculateIngredientCost(ingredient) }} جنيه
                          </div>
                        </div>
                      </div>
                    </div>
                    <div v-else class="text-red-600 font-bold">
                      🚫 لم يتم إضافة مكونات لهذا المنتج بعد
                    </div>
                  </div>
                </td>
              </tr>
            </template>
          </template>
          <tr v-else>
            <td colspan="7" class="text-center text-gray-500 py-8">لا يوجد منتجات صالحة للعرض أو هناك مشكلة في بيانات المنتجات.</td>
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
      expandedItems: new Set(),
    };
  },
  methods: {
    translateSize(size) {
      const translations = {
        'small': 'صغير',
        'medium': 'وسط',
        'large': 'كبير',
      };
      return translations[size] || size;
    },
    toggleDetails(key) {
      if (this.expandedItems.has(key)) {
        this.expandedItems.delete(key);
      } else {
        this.expandedItems.add(key);
      }
    },
    isExpanded(key) {
      return this.expandedItems.has(key);
    },
    getIngredientsForSize(product, size) {
      return product.ingredients.filter(ing => ing.pivot.size === size);
    },
    getCostPrice(product, size) {
      const ingredients = this.getIngredientsForSize(product, size);
      let totalCost = 0;
      for (const ingredient of ingredients) {
        const cost = this.calculateIngredientCost(ingredient);
        if (typeof cost === 'string') {
          // إذا كان هناك تحذير، أرجع التحذير مباشرة
          return cost;
        }
        totalCost += parseFloat(cost);
      }
      return totalCost.toFixed(2);
    },
    calculateIngredientCost(ingredient) {
      const price = ingredient.unit_price || ingredient.unit_consume_price;
      if (!price || price === 0) return '⚠️ لم يتم تحديد سعر وحدة الاستهلاك';
      return (ingredient.pivot.quantity_consumed * price).toFixed(2);
    },
    getProfitAmount(product, size) {
      const variant = (product.size_variants || []).find(v => v && v.size === size);
      if (!variant) return 0;
      
      const sellingPrice = parseFloat(variant.price);
      const costPrice = parseFloat(this.getCostPrice(product, size));
      
      return (sellingPrice - costPrice).toFixed(2);
    },
    getProfitMargin(product, size) {
      const variant = (product.size_variants || []).find(v => v && v.size === size);
      if (!variant) return 0;
      
      const sellingPrice = parseFloat(variant.price);
      const costPrice = parseFloat(this.getCostPrice(product, size));
      
      if (sellingPrice === 0) return 0;
      return ((sellingPrice - costPrice) / sellingPrice * 100).toFixed(1);
    },
    getProfitClass(product, size) {
      const profit = parseFloat(this.getProfitAmount(product, size));
      if (profit > 0) return 'text-green-700';
      if (profit < 0) return 'text-red-700';
      return 'text-gray-700';
    },
    getTotalRevenue() {
      let total = 0;
      this.products.forEach(product => {
        (product.size_variants || []).filter(v => v && v.size).forEach(variant => {
          total += parseFloat(variant.price);
        });
      });
      return total.toFixed(2);
    },
    getTotalCost() {
      let total = 0;
      this.products.forEach(product => {
        (product.size_variants || []).filter(v => v && v.size).forEach(variant => {
          total += parseFloat(this.getCostPrice(product, variant.size));
        });
      });
      return total.toFixed(2);
    },
    getTotalProfit() {
      return (parseFloat(this.getTotalRevenue()) - parseFloat(this.getTotalCost())).toFixed(2);
    },
    getAverageProfitMargin() {
      let totalMargin = 0;
      let count = 0;
      
      this.products.forEach(product => {
        (product.size_variants || []).filter(v => v && v.size).forEach(variant => {
          const margin = parseFloat(this.getProfitMargin(product, variant.size));
          totalMargin += margin;
          count++;
        });
      });
      
      return count > 0 ? (totalMargin / count).toFixed(1) : 0;
    },
  },
};
</script>

<style scoped>
.btn-blue {
  @apply bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition;
}
.btn-gray {
  @apply bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg transition;
}
</style> 