<template>
  <AppLayout title="تقارير صور التقفيلة">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">تقارير صور التقفيلة</h2>
    </template>

    <div class="py-10" dir="rtl">
      <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div v-if="flashSuccess" class="rounded-lg bg-green-50 text-green-800 px-4 py-3 text-sm">{{ flashSuccess }}</div>
        <div v-if="flashError" class="rounded-lg bg-red-50 text-red-800 px-4 py-3 text-sm">{{ flashError }}</div>

        <div class="bg-white shadow-xl sm:rounded-lg p-6 space-y-4">
          <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h3 class="text-lg font-semibold text-gray-900">تفعيل الخاصية ونوافذ الوقت</h3>
              <p class="text-sm text-gray-600">
                عند التفعيل، يُمنع المدير من فتح تقارير المبيعات أثناء نافذة التقفيلة حتى يرفع كل الصور الإجبارية.
              </p>
            </div>
            <label class="inline-flex items-center gap-2 text-sm font-medium">
              <input v-model="settingsForm.enabled" type="checkbox" class="rounded border-gray-300 text-indigo-600" />
              تفعيل تقارير صور التقفيلة
            </label>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
              <label class="block text-sm text-gray-700 mb-1">بداية التقفيلة الأولى</label>
              <input v-model="settingsForm.evening_starts_at" type="time" class="w-full rounded-lg border-gray-300 text-sm" />
            </div>
            <div>
              <label class="block text-sm text-gray-700 mb-1">نهاية التقفيلة الأولى</label>
              <input v-model="settingsForm.evening_ends_at" type="time" class="w-full rounded-lg border-gray-300 text-sm" />
            </div>
            <div>
              <label class="block text-sm text-gray-700 mb-1">بداية التقفيلة الثانية</label>
              <input v-model="settingsForm.dawn_starts_at" type="time" class="w-full rounded-lg border-gray-300 text-sm" />
            </div>
            <div>
              <label class="block text-sm text-gray-700 mb-1">نهاية التقفيلة الثانية</label>
              <input v-model="settingsForm.dawn_ends_at" type="time" class="w-full rounded-lg border-gray-300 text-sm" />
            </div>
          </div>

          <button
            type="button"
            class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm disabled:opacity-50"
            :disabled="settingsForm.processing"
            @click="saveSettings"
          >
            حفظ الإعدادات
          </button>
        </div>

        <div
          v-for="type in ['evening', 'dawn']"
          :key="type"
          class="bg-white shadow-xl sm:rounded-lg p-6 space-y-4"
        >
          <h3 class="text-lg font-semibold text-gray-900">{{ typeLabels[type] }}</h3>

          <form class="grid grid-cols-1 md:grid-cols-6 gap-3 items-end border-b pb-4" @submit.prevent="addItem(type)">
            <div class="md:col-span-2">
              <label class="block text-sm text-gray-700 mb-1">عنوان البند</label>
              <input v-model="newItems[type].title" type="text" required class="w-full rounded-lg border-gray-300 text-sm" placeholder="مثال: صورة درج الكاشير" />
            </div>
            <div class="md:col-span-2">
              <label class="block text-sm text-gray-700 mb-1">وصف (اختياري)</label>
              <input v-model="newItems[type].description" type="text" class="w-full rounded-lg border-gray-300 text-sm" />
            </div>
            <label class="inline-flex items-center gap-2 text-sm mb-2">
              <input v-model="newItems[type].is_required" type="checkbox" class="rounded border-gray-300 text-indigo-600" />
              إجباري
            </label>
            <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-2 rounded-lg text-sm">إضافة بند</button>
          </form>

          <div v-if="!(itemsByType[type] || []).length" class="text-sm text-gray-500 py-4">لا توجد بنود بعد.</div>

          <div v-for="item in itemsByType[type]" :key="item.id" class="border border-gray-200 rounded-xl p-4 space-y-3">
            <div class="grid grid-cols-1 md:grid-cols-6 gap-3 items-end">
              <div class="md:col-span-2">
                <label class="block text-xs text-gray-500 mb-1">العنوان</label>
                <input v-model="editForms[item.id].title" type="text" class="w-full rounded-lg border-gray-300 text-sm" />
              </div>
              <div class="md:col-span-2">
                <label class="block text-xs text-gray-500 mb-1">الوصف</label>
                <input v-model="editForms[item.id].description" type="text" class="w-full rounded-lg border-gray-300 text-sm" />
              </div>
              <div>
                <label class="block text-xs text-gray-500 mb-1">الترتيب</label>
                <input v-model.number="editForms[item.id].sort_order" type="number" min="0" class="w-full rounded-lg border-gray-300 text-sm" />
              </div>
              <div class="flex flex-wrap gap-3 text-sm pb-2">
                <label class="inline-flex items-center gap-1">
                  <input v-model="editForms[item.id].is_required" type="checkbox" class="rounded border-gray-300 text-indigo-600" />
                  إجباري
                </label>
                <label class="inline-flex items-center gap-1">
                  <input v-model="editForms[item.id].is_active" type="checkbox" class="rounded border-gray-300 text-indigo-600" />
                  مفعّل
                </label>
              </div>
            </div>
            <div class="flex gap-2">
              <button type="button" class="text-sm px-3 py-1.5 rounded-lg bg-indigo-600 text-white" @click="saveItem(item)">حفظ</button>
              <button type="button" class="text-sm px-3 py-1.5 rounded-lg border border-red-300 text-red-700" @click="removeItem(item)">حذف</button>
            </div>
          </div>
        </div>

        <div class="bg-white shadow-xl sm:rounded-lg p-6 space-y-4">
          <h3 class="text-lg font-semibold text-gray-900">آخر التسليمات من الفروع</h3>
          <div v-if="!recentSubmissions.length" class="text-sm text-gray-500">لا توجد تسليمات بعد.</div>
          <div v-for="row in recentSubmissions" :key="row.id" class="border border-gray-200 rounded-xl p-4 space-y-3">
            <div class="flex flex-wrap gap-3 text-sm items-center justify-between">
              <div class="space-y-1">
                <div class="font-semibold text-gray-900">{{ row.branch_name }} · {{ row.closing_type_label }}</div>
                <div class="text-gray-600">اليوم التشغيلي: {{ row.business_date }} · بواسطة: {{ row.submitted_by_name || '—' }}</div>
              </div>
              <span
                class="px-3 py-1 rounded-full text-xs font-semibold"
                :class="row.is_complete ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-900'"
              >
                {{ row.is_complete ? 'مكتمل' : 'غير مكتمل' }}
              </span>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-6 gap-2">
              <a
                v-for="photo in row.photos"
                :key="photo.id"
                :href="photo.url"
                target="_blank"
                rel="noopener"
                class="block border rounded-lg overflow-hidden bg-gray-50"
              >
                <img :src="photo.url" :alt="photo.item_title" class="w-full h-24 object-cover" />
                <div class="text-[11px] p-1 truncate text-gray-700">{{ photo.item_title }}</div>
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';

export default {
  layout: AppLayout,
  props: {
    settings: { type: Object, required: true },
    itemsByType: { type: Object, required: true },
    recentSubmissions: { type: Array, default: () => [] },
    typeLabels: { type: Object, required: true },
  },
  data() {
    const editForms = {};
    ['evening', 'dawn'].forEach((type) => {
      (this.itemsByType[type] || []).forEach((item) => {
        editForms[item.id] = {
          title: item.title,
          description: item.description || '',
          sort_order: item.sort_order,
          is_required: !!item.is_required,
          is_active: !!item.is_active,
        };
      });
    });

    return {
      settingsForm: useForm({
        enabled: !!this.settings.enabled,
        evening_starts_at: this.settings.evening_starts_at,
        evening_ends_at: this.settings.evening_ends_at,
        dawn_starts_at: this.settings.dawn_starts_at,
        dawn_ends_at: this.settings.dawn_ends_at,
      }),
      newItems: {
        evening: { title: '', description: '', is_required: true, is_active: true },
        dawn: { title: '', description: '', is_required: true, is_active: true },
      },
      editForms,
    };
  },
  computed: {
    flashSuccess() {
      return usePage().props.flash?.success || null;
    },
    flashError() {
      return usePage().props.flash?.error || null;
    },
  },
  watch: {
    itemsByType: {
      deep: true,
      handler(value) {
        ['evening', 'dawn'].forEach((type) => {
          (value[type] || []).forEach((item) => {
            if (!this.editForms[item.id]) {
              this.editForms[item.id] = {
                title: item.title,
                description: item.description || '',
                sort_order: item.sort_order,
                is_required: !!item.is_required,
                is_active: !!item.is_active,
              };
            }
          });
        });
      },
    },
  },
  methods: {
    saveSettings() {
      this.settingsForm.put(route('admin.closing-photo-reports.settings.update'), {
        preserveScroll: true,
      });
    },
    addItem(type) {
      const payload = {
        closing_type: type,
        title: this.newItems[type].title,
        description: this.newItems[type].description || null,
        is_required: this.newItems[type].is_required,
        is_active: true,
      };
      router.post(route('admin.closing-photo-reports.items.store'), payload, {
        preserveScroll: true,
        onSuccess: () => {
          this.newItems[type] = { title: '', description: '', is_required: true, is_active: true };
        },
      });
    },
    saveItem(item) {
      router.put(route('admin.closing-photo-reports.items.update', item.id), this.editForms[item.id], {
        preserveScroll: true,
      });
    },
    removeItem(item) {
      if (!confirm(`حذف البند «${item.title}»؟`)) return;
      router.delete(route('admin.closing-photo-reports.items.destroy', item.id), {
        preserveScroll: true,
      });
    },
  },
};
</script>
