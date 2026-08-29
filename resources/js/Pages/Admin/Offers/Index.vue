<template>
  <AppLayout title="إدارة العروض">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">🎁 إدارة العروض</h2>
    </template>

    <div class="py-8" dir="rtl">
      <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
          <p class="text-sm text-gray-600">
            أنشئ عروضاً مرنة: منتجات محددة، أو أي عدد من فئة، أو مجموعة منتجات — يُطبَّق الخصم تلقائياً في الكاشير.
          </p>
          <Link
            :href="route('admin.offers.create')"
            class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-semibold"
          >
            ➕ عرض جديد
          </Link>
        </div>

        <div v-if="offers.length === 0" class="bg-white rounded-xl shadow p-10 text-center text-gray-500">
          لا توجد عروض بعد.
        </div>

        <div v-for="offer in offers" :key="offer.id" class="bg-white rounded-xl shadow border overflow-hidden">
          <div class="p-5 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
              <div class="flex items-center gap-2 flex-wrap">
                <h3 class="text-lg font-bold text-gray-900">{{ offer.name }}</h3>
                <span
                  class="text-xs px-2 py-0.5 rounded-full"
                  :class="offer.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-600'"
                >
                  {{ offer.is_active ? 'نشط' : 'موقوف' }}
                </span>
                <span class="text-xs bg-indigo-100 text-indigo-800 px-2 py-0.5 rounded-full">
                  أولوية {{ offer.priority }}
                </span>
              </div>
              <p v-if="offer.description" class="text-sm text-gray-600 mt-1">{{ offer.description }}</p>
              <p class="text-sm text-gray-500 mt-1">{{ offer.rules_count }} شرط/شريحة</p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
              <div class="text-center bg-amber-50 px-4 py-2 rounded-lg">
                <div class="text-xl font-bold text-amber-700">{{ formatPrice(offer.offer_price) }}</div>
                <div class="text-xs text-amber-800">سعر العرض</div>
              </div>
              <Link
                :href="route('admin.offers.edit', offer.id)"
                class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-2 rounded-lg text-sm"
              >
                تعديل
              </Link>
              <button
                type="button"
                class="px-3 py-2 rounded-lg text-sm text-white"
                :class="offer.is_active ? 'bg-gray-500 hover:bg-gray-600' : 'bg-green-600 hover:bg-green-700'"
                @click="toggleOffer(offer)"
              >
                {{ offer.is_active ? 'إيقاف' : 'تفعيل' }}
              </button>
              <button
                type="button"
                class="bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded-lg text-sm"
                @click="deleteOffer(offer)"
              >
                حذف
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';

export default {
  layout: AppLayout,
  components: { Link },
  props: {
    offers: { type: Array, default: () => [] },
  },
  methods: {
    formatPrice(v) {
      return Number(v || 0).toFixed(2);
    },
    toggleOffer(offer) {
      router.post(route('admin.offers.toggle', offer.id), {}, { preserveScroll: true });
    },
    deleteOffer(offer) {
      if (!confirm(`حذف العرض «${offer.name}»؟`)) return;
      router.delete(route('admin.offers.destroy', offer.id));
    },
  },
};
</script>
