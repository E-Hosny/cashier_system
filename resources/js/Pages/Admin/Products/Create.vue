<template>
  <div class="container mx-auto p-6" dir="rtl">
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold text-gray-800">✨ إضافة منتج جديد</h1>
        <a :href="route('admin.products.index')" class="btn-gray">➡️ العودة إلى المنتجات</a>
    </div>

    <form @submit.prevent="submit" class="space-y-8">
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
                    <label class="block text-gray-700 mb-1">صورة المنتج</label>
                    <input type="file" @change="handleFileUpload" class="input-style" />
                </div>
            </div>
        </div>

        <!-- Size Variants & Their Ingredients -->
        <div class="bg-white shadow-md rounded-xl p-6 border">
            <div class="flex justify-between items-center mb-4">
              <h2 class="text-xl font-semibold text-gray-700">الأحجام والأسعار والمكونات</h2>
              <div class="flex gap-2">
                <button @click="activateAllSizes" type="button" class="btn-green text-sm">تفعيل الكل</button>
                <button @click="deactivateAllSizes" type="button" class="btn-gray text-sm">إلغاء تفعيل الكل</button>
              </div>
            </div>
            <div class="space-y-4">
                <div v-for="(variant, v_index) in form.size_variants" :key="v_index" class="p-4 border rounded-lg bg-gray-50">
                    <div class="flex flex-wrap items-center gap-4 mb-4">
                         <label class="flex items-center min-w-[120px] font-bold">
                            <input type="checkbox" v-model="variant.is_active" class="form-checkbox h-5 w-5 text-blue-600 mr-2" />
                            {{ variant.label }}
                        </label>
                        <div v-if="variant.is_active" class="flex-1">
                            <label class="block text-sm font-medium text-gray-600 mb-1">السعر</label>
                            <input type="number" v-model="variant.price" class="input-style" placeholder="السعر" step="0.01" required />
                        </div>
                    </div>

                    <!-- وصف الباريستا (الريسبي) -->
                    <div v-if="variant.is_active" class="mt-3">
                        <label class="block text-sm font-medium text-gray-600 mb-1">وصف الباريستا (الريسبي)</label>
                        <textarea
                            v-model="variant.barista_description"
                            class="input-style"
                            rows="3"
                            placeholder=""
                        ></textarea>
                    </div>
                    
                    <!-- Ingredients for this variant -->
                    <div v-if="variant.is_active" class="mt-4 pt-4 border-t">
                        <h3 class="font-semibold text-gray-600 mb-2">مكونات حجم ({{ variant.label }})</h3>
                        <div v-for="(ingredient, i_index) in variant.ingredients" :key="i_index" class="flex gap-4 items-end mb-4">
                            <div class="flex-1">
                                <label class="block text-gray-700 mb-1 text-sm">المادة الخام</label>
                                <select v-model="ingredient.raw_material_id" class="input-style">
                                    <option disabled value="">اختر المادة الخام</option>
                                    <option v-for="material in rawMaterials" :key="material.id" :value="material.id">{{ material.name }} ({{ material.unit }})</option>
                                </select>
                            </div>
                            <div class="w-1/4">
                                <label class="block text-gray-700 mb-1 text-sm">الكمية المستهلكة</label>
                                <input v-model="ingredient.quantity_consumed" type="number" step="0.001" class="input-style" placeholder="الكمية" />
                            </div>
                            <div class="w-1/4">
                                <label class="block text-gray-700 mb-1 text-sm">الوحدة</label>
                                <select v-model="ingredient.unit" class="input-style mb-1">
                                    <option value="مللي">مللي</option>
                                    <option value="لتر">لتر</option>
                                    <option value="جرام">جرام</option>
                                    <option value="كجم">كجم</option>
                                    <option value="قطعة">قطعة</option>
                                    <option value="custom">أخرى...</option>
                                </select>
                                <input v-if="ingredient.unit === 'custom'" v-model="ingredient.custom_unit" type="text" class="input-style mt-1" placeholder="أدخل وحدة مخصصة" />
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
            💾 حفظ المنتج
        </button>
    </form>
  </div>
</template>

<script>
import { Inertia } from '@inertiajs/inertia';
import AppLayout from '@/Layouts/AppLayout.vue';

export default {
    layout: AppLayout,
    props: {
        categories: Array,
        sizes: Array,
        rawMaterials: Array,
    },
    data() {
        return {
            form: {
                name: '',
                category_id: '',
                image: null,
                size_variants: this.sizes.map(s => ({
                    size: s.value,
                    label: s.label,
                    price: '',
                    is_active: false,
                    ingredients: [],
                    barista_description: '',
                })),
            },
        };
    },
    methods: {
        handleFileUpload(event) {
            this.form.image = event.target.files[0];
        },
        addIngredient(variant) {
            variant.ingredients.push({ raw_material_id: '', quantity_consumed: '', unit: 'مللي', custom_unit: '' });
        },
        removeIngredient(variant, index) {
            variant.ingredients.splice(index, 1);
        },
        activateAllSizes() {
            this.form.size_variants.forEach(v => v.is_active = true);
        },
        deactivateAllSizes() {
            this.form.size_variants.forEach(v => v.is_active = false);
        },
        submit() {
            const activeVariants = this.form.size_variants.filter(v => v.is_active);

            // Client-side validation
            if (!this.form.name || this.form.name.trim() === '') {
                alert('يرجى إدخال اسم المنتج.');
                return;
            }
            if (activeVariants.length === 0) {
                alert('يرجى تفعيل حجم واحد على الأقل وإدخال سعره.');
                return;
            }
            for (const variant of activeVariants) {
                if (!variant.price || isNaN(variant.price)) {
                    alert('يرجى إدخال سعر لكل حجم مفعّل.');
                    return;
                }
            }

            const formData = new FormData();
            formData.append('name', this.form.name);
            formData.append('category_id', this.form.category_id || '');
            if (this.form.image) {
                formData.append('image', this.form.image);
            }

            activeVariants.forEach((variant, v_index) => {
                formData.append(`size_variants[${v_index}][size]`, variant.size);
                formData.append(`size_variants[${v_index}][price]`, variant.price);
                formData.append(`size_variants[${v_index}][barista_description]`, variant.barista_description || '');
                variant.ingredients.forEach((ing, i_index) => {
                    const unit = ing.unit === 'custom' ? ing.custom_unit : ing.unit;
                    formData.append(`size_variants[${v_index}][ingredients][${i_index}][raw_material_id]`, ing.raw_material_id);
                    formData.append(`size_variants[${v_index}][ingredients][${i_index}][quantity_consumed]`, ing.quantity_consumed);
                    formData.append(`size_variants[${v_index}][ingredients][${i_index}][unit]`, unit);
                });
            });

            Inertia.post(route('admin.products.store'), formData);
        },
    },
};
</script>

<style scoped>
.input-style {
    @apply w-full p-3 border border-gray-300 rounded-lg focus:ring focus:ring-blue-300;
}

.btn-primary {
    @apply bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg transition shadow-md;
}

.btn-gray {
    @apply bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg transition;
}

.btn-green {
    @apply bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-lg transition;
}

.btn-red {
    @apply bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded-lg transition;
}
</style> 