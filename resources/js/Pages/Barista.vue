<template>
  <div class="min-h-dvh flex flex-col" dir="rtl">
    <!-- Header -->
    <div class="flex-shrink-0 bg-white border-b border-gray-200 p-2 px-4">
      <div class="flex justify-between items-center gap-2">
        <h1 class="text-xl font-extrabold text-gray-800">🧑‍🍳 واجهة الريسبي</h1>
        <div class="text-sm text-gray-500">اختر فئة ثم منتج + مقاس لعرض الريسبي</div>
      </div>
    </div>

    <div class="flex-1 flex flex-col lg:flex-row overflow-hidden">
      <!-- الفئات -->
      <div class="w-full lg:w-72 bg-gray-50 border-b lg:border-b-0 lg:border-l border-gray-200 flex-shrink-0 flex flex-col">
        <div class="p-3 flex-shrink-0">
          <h3 class="text-base font-semibold text-gray-800 text-center">📋 الفئات</h3>
        </div>

        <div class="flex-1 lg:overflow-y-auto overflow-x-auto px-3 pb-3">
          <div class="flex lg:flex-col gap-2 lg:gap-1 lg:space-y-1 min-w-max lg:min-w-0">
            <button
              type="button"
              class="cursor-pointer px-3 py-2 bg-blue-100 hover:bg-blue-200 rounded-lg text-center font-bold text-blue-800 shadow transition-colors text-sm whitespace-nowrap"
              :class="{ 'bg-blue-300': selectedCategoryId === null }"
              @click="selectCategory(null)"
            >
              كل المنتجات
            </button>

            <button
              v-for="cat in categories"
              :key="cat.id"
              type="button"
              class="cursor-pointer px-3 py-2 bg-white hover:bg-gray-100 rounded-lg text-center font-semibold shadow transition-colors border border-gray-200 text-sm whitespace-nowrap"
              :class="{ 'bg-green-200 border-green-300': selectedCategoryId === cat.id }"
              @click="selectCategory(cat.id)"
            >
              {{ cat.name }}
            </button>
          </div>
        </div>
      </div>

      <!-- المنتجات -->
      <div class="flex-1 flex flex-col overflow-hidden">
        <!-- Search -->
        <div class="flex-shrink-0 p-4 bg-white border-b border-gray-200">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="ابحث عن منتج..."
            class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          />
        </div>

        <!-- List -->
        <div class="flex-1 overflow-y-auto p-4">
          <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 gap-4">
            <div
              v-for="product in filteredProducts"
              :key="product.id"
              class="bg-white rounded-lg shadow-lg overflow-hidden transform transition-all hover:scale-105 flex flex-col border border-gray-200 text-sm"
            >
              <div class="p-3 flex-1 flex flex-col">
                <h3 class="text-sm font-semibold text-gray-800 text-center leading-tight">
                  {{ product.name }}
                </h3>

                <!-- Size selection -->
                <div v-if="hasVariants(product)" class="my-2 flex justify-center gap-1">
                  <button
                    v-for="(variant, v_idx) in product.size_variants"
                    :key="variant.size"
                    @click="selectVariant(product, v_idx)"
                    :class="[
                      'px-2 py-1 rounded-full text-xs font-semibold',
                      product.selectedVariantIndex === v_idx ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-700'
                    ]"
                  >
                    {{ translateSize(variant.size) }}
                  </button>
                </div>

                <p class="text-center text-green-700 font-bold mb-2" v-if="getSelectedVariant(product)">
                  {{ getSelectedVariant(product).price }} جنيه
                </p>

                <!-- Recipe/Description -->
                <div class="mt-auto">
                  <div class="rounded-xl border border-gray-200 bg-gradient-to-br from-indigo-50 via-white to-teal-50 p-3">
                    <div class="flex items-center justify-between gap-2 mb-2">
                      <div class="font-extrabold text-gray-800 text-sm">
                        🍽️ الريسبي - {{ getSelectedSizeLabel(product) }}
                      </div>
                      <div
                        class="text-[11px] text-gray-600 whitespace-nowrap"
                        v-if="getSelectedSizeNote(product)"
                      >
                        {{ getSelectedSizeNote(product) }}
                      </div>
                      <div
                        class="text-[11px] text-gray-500 whitespace-nowrap"
                        v-else
                      >
                        حسب المنتج والمقاس
                      </div>
                    </div>

                    <div
                      class="text-sm text-gray-800 whitespace-pre-wrap leading-relaxed max-h-36 overflow-y-auto"
                      v-if="getBaristaDescription(product) && getBaristaDescription(product).trim() !== ''"
                    >
                      {{ getBaristaDescription(product) }}
                    </div>

                    <div v-else class="text-sm text-gray-400">
                      غير محدد
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div v-if="filteredProducts.length === 0" class="col-span-full text-center text-gray-500 py-10">
              لا توجد منتجات حسب الفلاتر.
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  props: {
    products: Array,
    categories: Array,
  },
  data() {
    return {
      searchQuery: '',
      selectedCategoryId: null,
      liveProducts: [],
      sizeTranslations: {
        small: 'صغير',
        medium: 'وسط',
        large: 'كبير',
        extra_large: 'كان كبير',
      },
    };
  },
  computed: {
    filteredProducts() {
      return this.liveProducts
        .filter((p) => this.selectedCategoryId === null || p.category_id === this.selectedCategoryId)
        .filter((p) => p.name?.toLowerCase().includes(this.searchQuery.toLowerCase()));
    },
  },
  methods: {
    getSizeNote(size) {
      const notes = {
        small: 'كوب 12',
        medium: 'كوب 14',
        large: 'كوب 16',
        extra_large: 'كان 500 مللي',
      };
      return notes[size] || '';
    },
    getSelectedSizeNote(product) {
      const v = this.getSelectedVariant(product);
      return this.getSizeNote(v?.size);
    },
    initializeProducts() {
      this.liveProducts = this.products.map((p) => ({
        ...p,
        selectedVariantIndex: (p.size_variants && p.size_variants.length > 0) ? 0 : -1,
      }));
    },
    hasVariants(product) {
      return product.size_variants && product.size_variants.length > 0;
    },
    selectCategory(id) {
      this.selectedCategoryId = id;
    },
    selectVariant(product, variantIndex) {
      product.selectedVariantIndex = variantIndex;
    },
    translateSize(size) {
      return this.sizeTranslations[size] || size;
    },
    getSelectedVariant(product) {
      if (!product || product.selectedVariantIndex == null || product.selectedVariantIndex < 0) return null;
      return product.size_variants?.[product.selectedVariantIndex] || null;
    },
    getSelectedSizeLabel(product) {
      const v = this.getSelectedVariant(product);
      if (!v) return 'غير محدد';
      return this.translateSize(v.size);
    },
    getBaristaDescription(product) {
      const v = this.getSelectedVariant(product);
      return v?.barista_description || '';
    },
  },
  mounted() {
    this.initializeProducts();
  },
  watch: {
    products() {
      this.initializeProducts();
    },
  },
};
</script>

