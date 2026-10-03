<template>
  <div class="container mx-auto p-6" dir="rtl">
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold text-gray-800">🔄 تعديل المنتج</h1>
        <a :href="buildIndexHref()" class="btn-gray">➡️ العودة إلى المنتجات</a>
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
        <div class="flex justify-between items-center mb-4">
          <h2 class="text-xl font-semibold text-gray-700">الأحجام والأسعار والمكونات</h2>
          <div class="flex gap-2">
            <button @click="activateAllSizes" type="button" class="btn-green text-sm">تفعيل الكل</button>
            <button @click="deactivateAllSizes" type="button" class="btn-gray text-sm">إلغاء تفعيل الكل</button>
          </div>
        </div>
        <div class="space-y-6">
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
                          <div class="relative">
                            <input
                              v-model="ingredient.raw_material_query"
                              type="text"
                              class="input-style"
                              placeholder="اختر/اكتب المادة الخام"
                              @focus="openMaterialDropdown(ingredient)"
                              @click="openMaterialDropdown(ingredient)"
                              @input="onMaterialInput(ingredient)"
                              @blur="closeMaterialDropdown(ingredient)"
                            />
                            <div
                              v-if="ingredient.is_material_open"
                              class="absolute z-30 mt-1 w-full max-h-56 overflow-auto rounded-lg border border-gray-200 bg-white shadow-lg"
                            >
                              <button
                                v-for="material in filteredRawMaterials(ingredient.raw_material_query)"
                                :key="material.id"
                                type="button"
                                class="w-full px-3 py-2 text-right hover:bg-gray-100 text-sm"
                                @mousedown.prevent="selectMaterial(ingredient, material)"
                              >
                                {{ materialOptionLabel(material) }}
                              </button>
                              <div
                                v-if="filteredRawMaterials(ingredient.raw_material_query).length === 0"
                                class="px-3 py-2 text-sm text-gray-500"
                              >
                                لا توجد نتائج
                              </div>
                            </div>
                          </div>
                      </div>
                      <div class="w-1/4">
                          <label class="block text-gray-700 mb-1 text-sm">الكمية المستهلكة</label>
                          <input v-model="ingredient.quantity" type="number" step="0.001" class="input-style" placeholder="الكمية" />
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

      <!-- إضافات إجبارية في الكاشير -->
      <div class="bg-white shadow-md rounded-xl p-6 border space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h2 class="text-xl font-semibold text-gray-700">إضافات إجبارية (الكاشير)</h2>
            <p class="text-sm text-gray-600 mt-1">
              عند بيع هذا المنتج، يُطلب من الكاشير اختيار عدد محدد من المنتجات المربوطة.
              الإضافة تتسحب من المخزون، وسعرها الافتراضي صفر (مشمول في سعر المنتج الأساسي).
              يمكن تحديد «سعر إضافي» لبعض الإضافات فقط عند الحاجة.
            </p>
          </div>
          <button type="button" class="btn-green text-sm" @click="addAddonGroup">➕ مجموعة إضافات</button>
        </div>

        <div v-if="!addonGroupsForm.length" class="text-sm text-gray-500 py-2">
          لا توجد مجموعات إضافات — المنتج يُباع مباشرة بدون اختيار إجباري.
        </div>

        <div
          v-for="(group, gIndex) in addonGroupsForm"
          :key="'addon-group-' + gIndex"
          class="border-2 border-amber-300 rounded-xl p-4 bg-amber-50/50 space-y-4 shadow-sm"
        >
          <div class="flex flex-wrap items-center justify-between gap-2 border-b border-amber-200 pb-3">
            <div class="font-bold text-amber-950">
              مجموعة {{ gIndex + 1 }}:
              <span class="text-gray-900">{{ group.name || 'بدون اسم' }}</span>
              <span class="ms-2 text-xs font-semibold px-2 py-0.5 rounded-full bg-amber-200 text-amber-900">
                اختَر {{ group.required_count || 1 }}
              </span>
              <span class="ms-1 text-xs font-semibold px-2 py-0.5 rounded-full bg-white text-gray-700 border">
                {{ groupSizeLabel(group.size) }}
              </span>
            </div>
            <button type="button" class="btn-red text-sm" @click="removeAddonGroup(gIndex)">حذف المجموعة</button>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
            <div class="md:col-span-2">
              <label class="block text-sm text-gray-700 mb-1">اسم المجموعة (يظهر للكاشير)</label>
              <input v-model="group.name" type="text" class="input-style" placeholder="مثال: اختَر الصوص" />
            </div>
            <div>
              <label class="block text-sm text-gray-700 mb-1">عدد الاختيارات الإجبارية</label>
              <input v-model.number="group.required_count" type="number" min="1" max="20" class="input-style" />
            </div>
            <div>
              <label class="block text-sm text-gray-700 mb-1">ينطبق على الحجم</label>
              <select v-model="group.size" class="input-style">
                <option value="">كل الأحجام</option>
                <option v-for="s in sizes" :key="s.value" :value="s.value">{{ s.label }}</option>
              </select>
            </div>
          </div>

          <!-- الملخص الواضح للمنتجات المربوطة حالياً -->
          <div class="rounded-xl border border-emerald-300 bg-emerald-50 p-3 space-y-2">
            <div class="flex items-center justify-between gap-2">
              <h3 class="font-bold text-emerald-900 text-sm">
                المنتجات المربوطة حالياً ({{ group.options.length }})
              </h3>
              <span class="text-xs text-emerald-800">يمكن تعديل السعر أو الحذف من هنا مباشرة</span>
            </div>

            <div v-if="!group.options.length" class="text-sm text-emerald-900/70 py-2">
              لا يوجد منتجات مربوطة بعد — اختر من القائمة بالأسفل.
            </div>

            <div v-else class="space-y-2">
              <div
                v-for="opt in group.options"
                :key="'selected-' + gIndex + '-' + opt.addon_product_id"
                class="flex flex-wrap items-center gap-2 bg-white border border-emerald-200 rounded-lg px-3 py-2"
              >
                <div class="flex-1 min-w-[160px] font-semibold text-gray-900 text-sm">
                  {{ finishedProductName(opt.addon_product_id) }}
                </div>
                <div class="flex items-center gap-1">
                  <label class="text-xs text-gray-500 whitespace-nowrap">سعر إضافي</label>
                  <input
                    type="number"
                    min="0"
                    step="0.01"
                    class="w-24 rounded border-gray-300 text-sm py-1"
                    v-model.number="opt.extra_price"
                  />
                  <span class="text-xs text-gray-500">ج.م</span>
                </div>
                <span
                  class="text-[11px] font-semibold px-2 py-0.5 rounded-full"
                  :class="Number(opt.extra_price) > 0 ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'"
                >
                  {{ Number(opt.extra_price) > 0 ? ('+' + Number(opt.extra_price).toFixed(2)) : 'مشمول' }}
                </span>
                <button
                  type="button"
                  class="text-xs px-2 py-1 rounded bg-red-100 text-red-700 hover:bg-red-200"
                  @click="toggleAddonProduct(group, opt.addon_product_id)"
                >
                  إزالة
                </button>
              </div>
            </div>
          </div>

          <div>
            <label class="block text-sm text-gray-700 mb-1">إضافة / بحث عن منتجات للربط</label>
            <input
              v-model="group.search"
              type="text"
              class="input-style mb-2"
              placeholder="ابحث عن منتج لإضافته للمجموعة..."
            />
            <div class="max-h-48 overflow-y-auto border rounded-lg bg-white divide-y">
              <label
                v-for="fp in filteredFinishedForGroup(group)"
                :key="'fp-' + gIndex + '-' + fp.id"
                class="flex items-center gap-2 px-3 py-2 text-sm hover:bg-gray-50 cursor-pointer"
                :class="isAddonSelectedInGroup(group, fp.id) ? 'bg-emerald-50' : ''"
              >
                <input
                  type="checkbox"
                  class="rounded border-gray-300 text-indigo-600"
                  :checked="isAddonSelectedInGroup(group, fp.id)"
                  @change="toggleAddonProduct(group, fp.id)"
                />
                <span class="flex-1">{{ fp.name }}</span>
                <span v-if="isAddonSelectedInGroup(group, fp.id)" class="text-[11px] text-emerald-700 font-semibold">مربوط ✓</span>
              </label>
              <div v-if="!filteredFinishedForGroup(group).length" class="px-3 py-2 text-sm text-gray-500">لا نتائج</div>
            </div>
            <p class="text-xs text-gray-600 mt-1">
              اترك السعر الإضافي 0 لو الإضافة مشمولة في سعر المنتج الأساسي.
            </p>
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
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

export default {
    layout: AppLayout,
    props: {
        product: Object,
        categories: Array,
        sizes: Array,
        rawMaterials: Array,
        finishedProducts: { type: Array, default: () => [] },
        addonGroups: { type: Array, default: () => [] },
        ingredients_by_size: Object,
        backFilters: {
            type: Object,
            default: () => ({}),
        },
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
                        is_active: existingVariant ? true : false,
                        barista_description: existingVariant && existingVariant.barista_description ? existingVariant.barista_description : '',
                        ingredients: existingIngredients.map(ing => ({
                            id: ing.id,
                            quantity: ing.pivot.quantity_consumed,
                            unit: ing.pivot.unit || 'مللي',
                            custom_unit: (['مللي','لتر','جرام','كجم','قطعة'].includes(ing.pivot.unit) || !ing.pivot.unit) ? '' : ing.pivot.unit,
                            raw_material_query: this.getMaterialLabelById(ing.id),
                            is_material_open: false
                        }))
                    };
                }),
            },
            addonGroupsForm: (this.addonGroups || []).map((g) => ({
                name: g.name || 'إضافات إجبارية',
                required_count: g.required_count || 1,
                size: g.size || '',
                options: (g.options || g.addon_product_ids?.map((id) => ({ addon_product_id: id, extra_price: 0 })) || [])
                    .map((o) => ({
                        addon_product_id: Number(o.addon_product_id ?? o),
                        extra_price: Number(o.extra_price || 0),
                    })),
                search: '',
            })),
        };
    },
    methods: {
        addAddonGroup() {
            this.addonGroupsForm.push({
                name: 'إضافات إجبارية',
                required_count: 1,
                size: '',
                options: [],
                search: '',
            });
        },
        removeAddonGroup(index) {
            this.addonGroupsForm.splice(index, 1);
        },
        isAddonSelectedInGroup(group, productId) {
            return group.options.some((o) => Number(o.addon_product_id) === Number(productId));
        },
        addonExtraPrice(group, productId) {
            const opt = group.options.find((o) => Number(o.addon_product_id) === Number(productId));
            return opt ? opt.extra_price : 0;
        },
        setAddonExtraPrice(group, productId, value) {
            const opt = group.options.find((o) => Number(o.addon_product_id) === Number(productId));
            if (opt) {
                const n = parseFloat(value);
                opt.extra_price = Number.isNaN(n) || n < 0 ? 0 : n;
            }
        },
        toggleAddonProduct(group, productId) {
            const id = Number(productId);
            const idx = group.options.findIndex((o) => Number(o.addon_product_id) === id);
            if (idx >= 0) group.options.splice(idx, 1);
            else group.options.push({ addon_product_id: id, extra_price: 0 });
        },
        finishedProductName(productId) {
            const p = (this.finishedProducts || []).find((x) => Number(x.id) === Number(productId));
            return p?.name || (`منتج #${productId}`);
        },
        groupSizeLabel(size) {
            if (!size) return 'كل الأحجام';
            const found = (this.sizes || []).find((s) => s.value === size);
            return found ? found.label : size;
        },
        filteredFinishedForGroup(group) {
            const term = (group.search || '').trim().toLowerCase();
            return (this.finishedProducts || []).filter((p) => {
                if (!term) return true;
                return (p.name || '').toLowerCase().includes(term);
            });
        },
        buildIndexHref() {
            const base = route('admin.products.index');
            const params = new URLSearchParams();
            if (this.backFilters?.category_id) params.set('category_id', this.backFilters.category_id);
            if (this.backFilters?.searchTerm) params.set('searchTerm', this.backFilters.searchTerm);
            const qs = params.toString();
            return qs ? `${base}?${qs}` : base;
        },
        handleFileUpload(event) {
            this.form.image = event.target.files[0];
        },
        addIngredient(variant) {
            variant.ingredients.push({ id: '', quantity: '', unit: 'مللي', custom_unit: '', raw_material_query: '', is_material_open: false });
        },
        removeIngredient(variant, index) {
            variant.ingredients.splice(index, 1);
        },
        activateAllSizes() {
            this.form.size_variants.forEach(variant => variant.is_active = true);
        },
        deactivateAllSizes() {
            this.form.size_variants.forEach(variant => variant.is_active = false);
        },
        filteredRawMaterials(searchTerm) {
            const term = (searchTerm || '').trim().toLowerCase();
            if (!term) return this.rawMaterials;

            return this.rawMaterials.filter((material) =>
                material.name?.toLowerCase().includes(term) ||
                material.unit?.toLowerCase().includes(term)
            );
        },
        materialOptionLabel(material) {
            return `${material.name} (${material.unit})`;
        },
        getMaterialLabelById(materialId) {
            const material = this.rawMaterials.find((m) => String(m.id) === String(materialId));
            return material ? this.materialOptionLabel(material) : '';
        },
        onMaterialInput(ingredient) {
            ingredient.is_material_open = true;
            this.syncIngredientMaterialId(ingredient);
        },
        syncIngredientMaterialId(ingredient) {
            const selected = this.rawMaterials.find(
                (m) => this.materialOptionLabel(m) === ingredient.raw_material_query
            );
            ingredient.id = selected ? selected.id : '';
        },
        openMaterialDropdown(ingredient) {
            ingredient.is_material_open = true;
        },
        closeMaterialDropdown(ingredient) {
            setTimeout(() => {
                ingredient.is_material_open = false;
            }, 120);
        },
        selectMaterial(ingredient, material) {
            ingredient.raw_material_query = this.materialOptionLabel(material);
            ingredient.id = material.id;
            ingredient.is_material_open = false;
        },
        submitProduct() {
            if (!this.form.name || this.form.name.trim() === '') {
                alert('يرجى إدخال اسم المنتج.');
                return;
            }
            
            const activeVariants = this.form.size_variants.filter(v => v.is_active);
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
            formData.append("_method", "PUT");
            formData.append("name", this.form.name);
            formData.append("category_id", this.form.category_id || '');
            // تمرير فلاتر صفحة Index لصفحة index بعد الحفظ
            formData.append('back_category_id', this.backFilters?.category_id || '');
            formData.append('back_searchTerm', this.backFilters?.searchTerm || '');
            if (this.form.image) {
                formData.append("image", this.form.image);
            }
            
            activeVariants.forEach((variant, v_index) => {
                formData.append(`size_variants[${v_index}][size]`, variant.size);
                formData.append(`size_variants[${v_index}][price]`, variant.price);
                formData.append(
                  `size_variants[${v_index}][barista_description]`,
                  variant.barista_description || ''
                );
                variant.ingredients.forEach((ing, i_index) => {
                    const unit = ing.unit === 'custom' ? ing.custom_unit : ing.unit;
                    formData.append(`size_variants[${v_index}][ingredients][${i_index}][id]`, ing.id);
                    formData.append(`size_variants[${v_index}][ingredients][${i_index}][quantity]`, ing.quantity);
                    formData.append(`size_variants[${v_index}][ingredients][${i_index}][unit]`, unit);
                });
            });

            let addonIndex = 0;
            for (const group of this.addonGroupsForm) {
                if (!group.options.length) continue;
                if ((group.required_count || 1) > group.options.length) {
                    alert(`مجموعة «${group.name}»: عدد الاختيارات أكبر من المنتجات المتاحة.`);
                    return;
                }
                formData.append(`addon_groups[${addonIndex}][name]`, group.name || 'إضافات إجبارية');
                formData.append(`addon_groups[${addonIndex}][required_count]`, String(group.required_count || 1));
                formData.append(`addon_groups[${addonIndex}][size]`, group.size || '');
                group.options.forEach((opt, pIndex) => {
                    formData.append(
                        `addon_groups[${addonIndex}][options][${pIndex}][addon_product_id]`,
                        String(opt.addon_product_id)
                    );
                    formData.append(
                        `addon_groups[${addonIndex}][options][${pIndex}][extra_price]`,
                        String(opt.extra_price || 0)
                    );
                });
                addonIndex += 1;
            }

            router.post(route("admin.products.update", this.product.id), formData, {
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