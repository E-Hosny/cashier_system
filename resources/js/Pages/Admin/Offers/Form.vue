<template>
  <AppLayout :title="isEdit ? 'تعديل عرض' : 'عرض جديد'">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ isEdit ? '🔄 تعديل عرض' : '➕ عرض جديد' }}
      </h2>
    </template>

    <div class="py-8" dir="rtl">
      <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <form @submit.prevent="submit" class="bg-white rounded-xl shadow p-6 space-y-6">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-1">اسم العرض *</label>
              <input v-model="form.name" type="text" required class="w-full border rounded-lg px-3 py-2" />
            </div>
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-1">الوصف</label>
              <textarea v-model="form.description" rows="2" class="w-full border rounded-lg px-3 py-2"></textarea>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">سعر العرض *</label>
              <input v-model.number="form.offer_price" type="number" min="0" step="0.01" required class="w-full border rounded-lg px-3 py-2" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">الأولوية</label>
              <input v-model.number="form.priority" type="number" min="0" max="999" class="w-full border rounded-lg px-3 py-2" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">يبدأ من</label>
              <input v-model="form.starts_at" type="datetime-local" class="w-full border rounded-lg px-3 py-2" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">ينتهي في</label>
              <input v-model="form.ends_at" type="datetime-local" class="w-full border rounded-lg px-3 py-2" />
            </div>
            <div class="md:col-span-2">
              <label class="inline-flex items-center gap-2 text-sm">
                <input v-model="form.is_active" type="checkbox" class="rounded" />
                العرض نشط
              </label>
            </div>
          </div>

          <hr />

          <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-800">شروط العرض</h3>
            <button type="button" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-lg text-sm" @click="addRule">
              ➕ إضافة شرط
            </button>
          </div>

          <div v-if="form.rules.length === 0" class="text-center text-gray-500 py-6 border border-dashed rounded-lg">
            أضف شرطاً واحداً على الأقل.
          </div>

          <div
            v-for="(rule, ruleIndex) in form.rules"
            :key="ruleIndex"
            class="border border-gray-200 rounded-xl p-4 space-y-4 bg-gray-50"
          >
            <div class="flex items-center justify-between">
              <h4 class="font-semibold text-gray-800">الشرط {{ ruleIndex + 1 }}</h4>
              <button type="button" class="text-red-600 text-sm" @click="removeRule(ruleIndex)">حذف</button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">نوع الشرط</label>
                <select v-model="rule.rule_type" class="w-full border rounded-lg px-3 py-2" @change="onRuleTypeChange(rule)">
                  <option value="fixed_products">منتجات محددة (بالكمية)</option>
                  <option value="category_pick">أي X من فئة</option>
                  <option value="product_pick">أي X من مجموعة منتجات</option>
                </select>
              </div>

              <div v-if="rule.rule_type === 'category_pick'">
                <label class="block text-sm font-medium text-gray-700 mb-1">الفئة</label>
                <select v-model="rule.category_id" class="w-full border rounded-lg px-3 py-2" required @change="onCategoryChange(rule)">
                  <option :value="null">— اختر فئة —</option>
                  <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                </select>
              </div>

              <div v-if="rule.rule_type === 'category_pick' && categoryHasSizes(rule.category_id)">
                <label class="block text-sm font-medium text-gray-700 mb-1">الحجم *</label>
                <select v-model="rule.size" class="w-full border rounded-lg px-3 py-2" required>
                  <option :value="null">— اختر الحجم —</option>
                  <option
                    v-for="size in categorySizes(rule.category_id)"
                    :key="size"
                    :value="size"
                  >
                    {{ translateSize(size) }}
                  </option>
                </select>
              </div>

              <div v-if="rule.rule_type !== 'fixed_products'">
                <label class="block text-sm font-medium text-gray-700 mb-1">العدد المطلوب</label>
                <input v-model.number="rule.quantity" type="number" min="1" max="50" class="w-full border rounded-lg px-3 py-2" />
              </div>
            </div>

            <div v-if="rule.rule_type === 'fixed_products' || rule.rule_type === 'product_pick'" class="space-y-2">
              <div class="flex items-center justify-between">
                <label class="text-sm font-medium text-gray-700">
                  {{ rule.rule_type === 'fixed_products' ? 'المنتجات والكميات' : 'المنتجات المؤهلة' }}
                </label>
                <button type="button" class="text-blue-600 text-sm" @click="addProductToRule(rule)">➕ منتج</button>
              </div>

              <div
                v-for="(row, rowIndex) in rule.products"
                :key="rowIndex"
                class="flex flex-col sm:flex-row gap-2 items-start sm:items-center bg-white p-2 rounded-lg border"
              >
                <select
                  v-model="row.product_id"
                  class="flex-1 border rounded-lg px-2 py-1.5 text-sm"
                  required
                  @change="onProductChange(row)"
                >
                  <option :value="null">— منتج —</option>
                  <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
                <select
                  v-if="productHasSizes(row.product_id)"
                  v-model="row.size"
                  class="w-36 border rounded-lg px-2 py-1.5 text-sm"
                  required
                >
                  <option :value="null">— الحجم —</option>
                  <option
                    v-for="size in productSizes(row.product_id)"
                    :key="size"
                    :value="size"
                  >
                    {{ translateSize(size) }}
                  </option>
                </select>
                <span
                  v-else-if="row.product_id"
                  class="text-xs text-gray-400 w-36 text-center"
                >
                  بدون أحجام
                </span>
                <input
                  v-if="rule.rule_type === 'fixed_products'"
                  v-model.number="row.quantity"
                  type="number"
                  min="1"
                  class="w-24 border rounded-lg px-2 py-1.5 text-sm"
                  placeholder="كمية"
                />
                <button type="button" class="text-red-500 text-sm px-2" @click="rule.products.splice(rowIndex, 1)">×</button>
              </div>
            </div>
          </div>

          <div class="flex gap-3 pt-4 border-t">
            <button type="submit" :disabled="submitting" class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg font-semibold disabled:opacity-50">
              {{ submitting ? 'جاري الحفظ...' : '💾 حفظ العرض' }}
            </button>
            <Link :href="route('admin.offers.index')" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg font-semibold">
              إلغاء
            </Link>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { translateSize } from '@/utils/productSizes';

export default {
  layout: AppLayout,
  components: { Link },
  props: {
    offer: { type: Object, default: null },
    categories: { type: Array, default: () => [] },
    products: { type: Array, default: () => [] },
  },
  data() {
    return {
      submitting: false,
      form: this.buildForm(),
    };
  },
  computed: {
    isEdit() {
      return !!this.offer?.id;
    },
    productsById() {
      return Object.fromEntries((this.products || []).map((p) => [p.id, p]));
    },
  },
  methods: {
    translateSize,
    buildForm() {
      if (this.offer) {
        return {
          name: this.offer.name,
          description: this.offer.description || '',
          offer_price: this.offer.offer_price,
          is_active: this.offer.is_active,
          priority: this.offer.priority ?? 0,
          starts_at: this.offer.starts_at || '',
          ends_at: this.offer.ends_at || '',
          rules: (this.offer.rules || []).map((r) => ({
            rule_type: r.rule_type,
            quantity: r.quantity || 1,
            category_id: r.category_id,
            size: r.size || null,
            products: (r.products || []).map((p) => ({
              product_id: p.product_id,
              quantity: p.quantity || 1,
              size: p.size || null,
            })),
          })),
        };
      }

      return {
        name: '',
        description: '',
        offer_price: 0,
        is_active: true,
        priority: 0,
        starts_at: '',
        ends_at: '',
        rules: [this.emptyRule('fixed_products')],
      };
    },
    emptyRule(type = 'fixed_products') {
      return {
        rule_type: type,
        quantity: 1,
        category_id: null,
        size: null,
        products: [{ product_id: null, quantity: 1, size: null }],
      };
    },
    productSizes(productId) {
      if (!productId) return [];
      const product = this.productsById[productId] || this.productsById[Number(productId)];
      if (!product?.size_variants?.length) return [];
      return product.size_variants
        .map((v) => v?.size)
        .filter((size) => !!size);
    },
    productHasSizes(productId) {
      return this.productSizes(productId).length > 0;
    },
    categorySizes(categoryId) {
      if (!categoryId) return [];
      const sizes = [];
      (this.products || []).forEach((product) => {
        if (Number(product.category_id) !== Number(categoryId)) return;
        (product.size_variants || []).forEach((variant) => {
          if (variant?.size && !sizes.includes(variant.size)) {
            sizes.push(variant.size);
          }
        });
      });
      const order = ['small', 'medium', 'large', 'extra_large'];
      return sizes.sort((a, b) => {
        const ai = order.indexOf(a);
        const bi = order.indexOf(b);
        if (ai === -1 && bi === -1) return a.localeCompare(b);
        if (ai === -1) return 1;
        if (bi === -1) return -1;
        return ai - bi;
      });
    },
    categoryHasSizes(categoryId) {
      return this.categorySizes(categoryId).length > 0;
    },
    onCategoryChange(rule) {
      const sizes = this.categorySizes(rule.category_id);
      if (!sizes.length) {
        rule.size = null;
        return;
      }
      if (!sizes.includes(rule.size)) {
        rule.size = sizes.length === 1 ? sizes[0] : null;
      }
    },
    onProductChange(row) {
      const sizes = this.productSizes(row.product_id);
      if (!sizes.length) {
        row.size = null;
        return;
      }
      if (!sizes.includes(row.size)) {
        row.size = sizes.length === 1 ? sizes[0] : null;
      }
    },
    addRule() {
      this.form.rules.push(this.emptyRule('category_pick'));
    },
    removeRule(index) {
      this.form.rules.splice(index, 1);
    },
    onRuleTypeChange(rule) {
      rule.category_id = null;
      rule.size = null;
      rule.quantity = 1;
      if (rule.rule_type === 'category_pick') {
        rule.products = [];
      } else {
        rule.products = [{ product_id: null, quantity: 1, size: null }];
      }
    },
    addProductToRule(rule) {
      rule.products.push({ product_id: null, quantity: 1, size: null });
    },
    validateSizes() {
      for (const [ruleIndex, rule] of this.form.rules.entries()) {
        if (rule.rule_type === 'category_pick' && this.categoryHasSizes(rule.category_id) && !rule.size) {
          alert(`اختر الحجم للشرط ${ruleIndex + 1}`);
          return false;
        }
        if (!['fixed_products', 'product_pick'].includes(rule.rule_type)) continue;
        for (const [rowIndex, row] of (rule.products || []).entries()) {
          if (!this.productHasSizes(row.product_id)) continue;
          if (!row.size) {
            alert(`اختر الحجم للمنتج في الشرط ${ruleIndex + 1} (صف ${rowIndex + 1})`);
            return false;
          }
        }
      }
      return true;
    },
    submit() {
      if (this.form.rules.length === 0) {
        alert('أضف شرطاً واحداً على الأقل');
        return;
      }
      if (!this.validateSizes()) return;

      this.submitting = true;
      const url = this.isEdit
        ? route('admin.offers.update', this.offer.id)
        : route('admin.offers.store');
      const method = this.isEdit ? 'put' : 'post';

      router[method](url, this.form, {
        onFinish: () => { this.submitting = false; },
      });
    },
  },
};
</script>
