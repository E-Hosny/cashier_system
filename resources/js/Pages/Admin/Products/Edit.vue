<template>
  <div class="container mx-auto p-6" dir="rtl">
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold text-gray-800">🔄 تعديل المنتج</h1>
        <a :href="route('admin.products.index')" class="btn-gray">➡️ العودة إلى المنتجات</a>
    </div>

    <div class="bg-white shadow-md rounded-xl p-6 mb-10 border">
      <form @submit.prevent="submitProduct" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-gray-700 mb-1">اسم المنتج</label>
            <input v-model="form.name" type="text" class="input-style" required />
          </div>

          <div>
            <label class="block text-gray-700 mb-1">الكمية</label>
            <input v-model="form.quantity" type="number" class="input-style" />
          </div>

          <div>
            <label class="block text-gray-700 mb-1">الفئة</label>
            <select v-model="form.category_id" class="input-style">
              <option disabled value="">اختر فئة</option>
              <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
            </select>
          </div>

          <div class="md:col-span-2">
            <label class="block text-gray-700 mb-2">الأحجام والأسعار</label>
            <div class="space-y-4">
              <div v-for="size in sizes" :key="size.value" class="flex flex-wrap items-center gap-4 p-4 border rounded-lg">
                <label class="flex items-center min-w-[120px]">
                  <input 
                    type="checkbox" 
                    :value="size.value"
                    v-model="selectedSizes"
                    class="form-checkbox h-5 w-5 text-blue-600"
                    @change="handleSizeChange(size.value)"
                  />
                  <span class="mr-2">{{ size.label }}</span>
                </label>
                <div v-if="isSizeSelected(size.value)" class="flex-1">
                  <div class="flex items-center gap-2">
                    <input 
                      type="number" 
                      v-model="getSizeVariant(size.value).price" 
                      class="input-style" 
                      placeholder="السعر" 
                      step="0.01"
                      required
                    />
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="md:col-span-2">
            <label class="block text-gray-700 mb-1">صورة المنتج (اتركه فارغاً للاحتفاظ بالصورة الحالية)</label>
            <input type="file" @change="handleFileUpload" class="input-style" />
          </div>
        </div>

        <button type="submit" class="btn-primary w-full">
          🔄 تحديث المنتج
        </button>
      </form>
    </div>
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
    },
    data() {
        return {
            form: { ...this.product, image: null },
            selectedSizes: this.product.available_sizes || [],
        };
    },
    methods: {
        handleFileUpload(event) {
            this.form.image = event.target.files[0];
        },
        isSizeSelected(size) {
            return this.selectedSizes.includes(size);
        },
        getSizeVariant(size) {
            let variant = this.form.size_variants.find(v => v.size === size);
            if (!variant) {
                variant = { size, price: '' };
                this.form.size_variants.push(variant);
            }
            return variant;
        },
        handleSizeChange(size) {
            if (this.selectedSizes.includes(size)) {
                if (!this.form.size_variants.some(v => v.size === size)) {
                    this.form.size_variants.push({ size, price: '' });
                }
            } else {
                this.form.size_variants = this.form.size_variants.filter(v => v.size !== size);
            }
        },
        submitProduct() {
            const formData = new FormData();
            formData.append("_method", "PUT");
            formData.append("name", this.form.name);
            formData.append("quantity", this.form.quantity !== null && this.form.quantity !== '' ? this.form.quantity : '');
            formData.append("category_id", this.form.category_id !== null && this.form.category_id !== '' ? this.form.category_id : '');
            
            this.form.size_variants.forEach((variant, index) => {
                formData.append(`size_variants[${index}][size]`, variant.size);
                formData.append(`size_variants[${index}][price]`, variant.price);
            });

            if (this.form.image) {
                formData.append("image", this.form.image);
            }

            Inertia.post(route("admin.products.update", this.product.id), formData);
        }
    }
}
</script>

<style scoped>
.input-style {
  @apply w-full p-3 border border-gray-300 rounded-lg focus:ring focus:ring-blue-300;
}
.btn-primary {
  @apply bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition;
}
.btn-gray {
  @apply bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded transition;
}
</style> 