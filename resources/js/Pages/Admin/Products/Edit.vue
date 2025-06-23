<template>
  <div class="container mx-auto p-6" dir="rtl">
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold text-gray-800">🔄 تعديل المنتج</h1>
        <a :href="route('admin.products.index')" class="btn-gray">➡️ العودة إلى المنتجات</a>
    </div>

    <form @submit.prevent="submitProduct" class="space-y-8">
      <!-- Product Details -->
      <div class="bg-white shadow-md rounded-xl p-6 border">
        <h2 class="text-xl font-semibold text-gray-700 mb-4">تفاصيل المنتج</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label class="block text-gray-700 mb-1">اسم المنتج</label>
            <input v-model="form.name" type="text" class="input-style" required />
          </div>
          <div>
            <label class="block text-gray-700 mb-1">الفئة</label>
            <select v-model="form.category_id" class="input-style">
              <option disabled value="">اختر فئة</option>
              <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
            </select>
          </div>
          <div class="md:col-span-2">
            <label class="block text-gray-700 mb-1">صورة المنتج (اتركه فارغاً للاحتفاظ بالصورة الحالية)</label>
            <input type="file" @change="handleFileUpload" class="input-style" />
          </div>
        </div>
      </div>

      <!-- Size Variants & Their Ingredients -->
      <div class="bg-white shadow-md rounded-xl p-6 border">
        <h2 class="text-xl font-semibold text-gray-700 mb-4">الأحجام والأسعار والمكونات</h2>
        <div class="space-y-6">
          <div v-for="(variant, v_index) in form.size_variants" :key="v_index" class="p-4 border rounded-lg bg-gray-50">
             <div class="flex flex-wrap items-center gap-4">
                <label class="flex items-center min-w-[120px] font-bold text-lg">{{ variant.label }}</label>
                <div class="flex-1">
                  <label class="block text-sm font-medium text-gray-600 mb-1">السعر</label>
                  <input type="number" v-model="variant.price" class="input-style" placeholder="السعر" step="0.01" required />
                </div>
              </div>
              
              <!-- Ingredients for this variant -->
              <div class="mt-4 pt-4 border-t">
                  <h3 class="font-semibold text-gray-600 mb-2">مكونات حجم ({{ variant.label }})</h3>
                  <div v-for="(ingredient, i_index) in variant.ingredients" :key="i_index" class="flex gap-4 items-end mb-4">
                      <div class="flex-1">
                          <label class="block text-gray-700 mb-1 text-sm">المادة الخام</label>
                          <select v-model="ingredient.id" class="input-style">
                              <option disabled value="">اختر المادة الخام</option>
                              <option v-for="material in rawMaterials" :key="material.id" :value="material.id">{{ material.name }} ({{ material.unit }})</option>
                          </select>
                      </div>
                      <div class="w-1/4">
                          <label class="block text-gray-700 mb-1 text-sm">الكمية المستهلكة</label>
                          <input v-model="ingredient.quantity" type="number" step="0.001" class="input-style" placeholder="الكمية" />
                      </div>
                      <div>
                          <button @click="removeIngredient(variant, i_index)" type="button" class="btn-red h-12">🗑️</button>
                      </div>
                  </div>
                  <button @click="addIngredient(variant)" type="button" class="btn-green">➕ إضافة مكون لـ ({{ variant.label }})</button>
              </div>
          </div>
        </div>
      </div>


      <button type="submit" class="btn-primary w-full !mt-8">
        🔄 تحديث المنتج
      </button>
    </form>
  </div>
</template>

<script>
import { Inertia } from "@inertiajs/inertia";
import AppLayout from '@/Layouts/AppLayout.vue';

export default {
    layout: AppLayout,
    props: {
        product: Object,
        categories: Array,
        sizes: Array,
        rawMaterials: Array,
        ingredients_by_size: Object,
    },
    data() {
        return {
            form: {
                ...this.product,
                image: null,
                size_variants: this.sizes.map(sizeInfo => {
                    const existingVariant = this.product.size_variants.find(v => v.size === sizeInfo.value);
                    const existingIngredients = this.ingredients_by_size[sizeInfo.value] || [];

                    return {
                        size: sizeInfo.value,
                        label: sizeInfo.label,
                        price: existingVariant ? existingVariant.price : '',
                        ingredients: existingIngredients.map(ing => ({
                            id: ing.id,
                            quantity: ing.pivot.quantity_consumed
                        }))
                    };
                }),
            },
        };
    },
    methods: {
        handleFileUpload(event) {
            this.form.image = event.target.files[0];
        },
        addIngredient(variant) {
            variant.ingredients.push({ id: '', quantity: '' });
        },
        removeIngredient(variant, index) {
            variant.ingredients.splice(index, 1);
        },
        submitProduct() {
            const formData = new FormData();
            formData.append("_method", "PUT");
            formData.append("name", this.form.name);
            formData.append("category_id", this.form.category_id || '');
            if (this.form.image) {
                formData.append("image", this.form.image);
            }
            
            // Filter out variants that don't have a price, as they are considered "inactive"
            const activeVariants = this.form.size_variants.filter(v => v.price !== '' && v.price !== null);

            activeVariants.forEach((variant, v_index) => {
                formData.append(`size_variants[${v_index}][size]`, variant.size);
                formData.append(`size_variants[${v_index}][price]`, variant.price);
                variant.ingredients.forEach((ing, i_index) => {
                    formData.append(`size_variants[${v_index}][ingredients][${i_index}][id]`, ing.id);
                    formData.append(`size_variants[${v_index}][ingredients][${i_index}][quantity]`, ing.quantity);
                });
            });

            Inertia.post(route("admin.products.update", this.product.id), formData, {
                forceFormData: true,
            });
        }
    }
}
</script>

<style scoped>
.input-style { @apply w-full p-3 border border-gray-300 rounded-lg focus:ring focus:ring-blue-300; }
.btn-primary { @apply bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg transition shadow-md; }
.btn-gray { @apply bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg transition; }
.btn-green { @apply bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-lg transition; }
.btn-red { @apply bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded-lg transition; }
</style> 