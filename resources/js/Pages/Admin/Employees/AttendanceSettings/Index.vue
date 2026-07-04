<template>
  <AppLayout title="إعدادات الحضور والخصومات">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">⏰ إعدادات الحضور والخصومات</h2>
    </template>

    <div class="py-12" dir="rtl">
      <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div v-if="$page.props.flash?.success" class="bg-green-100 border border-green-300 text-green-900 px-4 py-3 rounded-lg text-sm">
          {{ $page.props.flash.success }}
        </div>

        <div class="bg-white shadow-xl sm:rounded-lg p-6">
          <div class="flex flex-wrap gap-2 mb-6 border-b pb-4">
            <button
              type="button"
              class="px-4 py-2 rounded-lg font-semibold text-sm transition"
              :class="activeTab === 'schedules' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
              @click="activeTab = 'schedules'"
            >
              📅 مواعيد الحضور والانصراف
            </button>
            <button
              type="button"
              class="px-4 py-2 rounded-lg font-semibold text-sm transition"
              :class="activeTab === 'rules' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
              @click="activeTab = 'rules'"
            >
              ⚖️ قوانين خصم التأخير
            </button>
            <Link
              :href="route('admin.employees.index')"
              class="mr-auto text-sm text-blue-600 hover:text-blue-800 underline self-center"
            >
              ← العودة للموظفين
            </Link>
          </div>

          <!-- مواعيد الحضور -->
          <div v-show="activeTab === 'schedules'">
            <p class="text-sm text-gray-600 mb-4">
              حدّد موعد الحضور لكل موظف. <strong>كل القوانين النشطة</strong> تُطبَّق تلقائياً على أي موظف له موعد حضور،
              ما لم تُلغِ «تطبيق القوانين» لاستثنائه من الخصومات.
            </p>

            <form @submit.prevent="saveSchedules">
              <div class="overflow-x-auto">
                <table class="w-full text-sm text-right border border-gray-200 rounded-lg overflow-hidden">
                  <thead class="bg-gray-100">
                    <tr>
                      <th class="p-3">الموظف</th>
                      <th class="p-3">موعد الحضور</th>
                      <th class="p-3">موعد الانصراف</th>
                      <th class="p-3">سماح (د)</th>
                      <th class="p-3" title="أزل التحديد لاستثناء الموظف من كل قوانين الخصم">تطبيق القوانين</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="row in scheduleRows" :key="row.id" class="border-t">
                      <td class="p-3 font-medium">
                        {{ row.name }}
                        <span v-if="row.position" class="block text-xs text-gray-500">{{ row.position }}</span>
                        <span v-if="!row.late_deductions_enabled" class="block text-xs text-amber-700 mt-0.5">مستثنى من الخصومات</span>
                      </td>
                      <td class="p-3">
                        <input v-model="row.expected_checkin_time" type="time" class="border rounded p-2 w-full min-w-[7rem]" />
                      </td>
                      <td class="p-3">
                        <input v-model="row.expected_checkout_time" type="time" class="border rounded p-2 w-full min-w-[7rem]" />
                      </td>
                      <td class="p-3">
                        <input v-model.number="row.grace_minutes" type="number" min="0" max="120" class="border rounded p-2 w-20 text-center" />
                      </td>
                      <td class="p-3 text-center">
                        <input v-model="row.late_deductions_enabled" type="checkbox" class="rounded" title="تطبيق قوانين الخصم على هذا الموظف" />
                      </td>
                    </tr>
                    <tr v-if="!scheduleRows.length">
                      <td colspan="5" class="p-6 text-center text-gray-500">لا يوجد موظفون نشطون.</td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <div class="mt-4 flex justify-end">
                <button
                  type="submit"
                  class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg disabled:opacity-50"
                  :disabled="scheduleForm.processing"
                >
                  💾 حفظ المواعيد
                </button>
              </div>
            </form>
          </div>

          <!-- قوانين الخصم -->
          <div v-show="activeTab === 'rules'">
            <p class="text-sm text-gray-600 mb-4">
              القوانين هنا <strong>عامة لكل الموظفين</strong> الذين لهم موعد حضور ومفعّل لهم «تطبيق القوانين».
              عند التأخير يُطبَّق أول نطاق مطابق (مرة واحدة في يوم العمل).
            </p>

            <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 mb-6">
              <h3 class="font-bold text-gray-800 mb-3">{{ editingRuleId ? 'تعديل قانون' : 'قانون جديد' }}</h3>
              <form @submit.prevent="submitRule" class="space-y-4">
                <div>
                  <label class="block text-xs font-medium text-gray-600 mb-2">نوع القانون *</label>
                  <div class="flex flex-wrap gap-4">
                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                      <input v-model="ruleForm.rule_type" type="radio" value="range" class="rounded-full" />
                      نطاق (من – إلى)
                    </label>
                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                      <input v-model="ruleForm.rule_type" type="radio" value="more_than" class="rounded-full" />
                      أكثر من عدد دقائق
                    </label>
                  </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
                  <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">اسم (اختياري)</label>
                    <input v-model="ruleForm.name" type="text" class="w-full border rounded-lg p-2" />
                  </div>

                  <template v-if="ruleForm.rule_type === 'range'">
                    <div>
                      <label class="block text-xs font-medium text-gray-600 mb-1">من (دقيقة) *</label>
                      <input v-model.number="ruleForm.min_late_minutes" type="number" min="1" required class="w-full border rounded-lg p-2" />
                    </div>
                    <div>
                      <label class="block text-xs font-medium text-gray-600 mb-1">إلى (دقيقة) *</label>
                      <input v-model.number="ruleForm.max_late_minutes" type="number" min="1" required class="w-full border rounded-lg p-2" />
                    </div>
                  </template>

                  <template v-else>
                    <div class="md:col-span-2">
                      <label class="block text-xs font-medium text-gray-600 mb-1">أكثر من (دقيقة) *</label>
                      <input v-model.number="ruleForm.min_late_minutes" type="number" min="1" required class="w-full border rounded-lg p-2" placeholder="مثال: 60 = يطبق من 61 دقيقة فأكثر" />
                      <p class="text-xs text-gray-500 mt-1">مثال: 60 يعني التأخير أكثر من 60 د (أي من 61 دقيقة فأكثر).</p>
                    </div>
                  </template>

                  <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">مبلغ الخصم (ج) *</label>
                    <input v-model.number="ruleForm.deduction_amount" type="number" min="0.01" step="0.01" required class="w-full border rounded-lg p-2" />
                  </div>
                </div>

                <div class="flex flex-wrap items-center gap-4">
                  <label class="flex items-center gap-2 text-sm">
                    <input v-model="ruleForm.is_active" type="checkbox" class="rounded" />
                    نشط
                  </label>
                  <div class="flex items-center gap-2 text-sm">
                    <span>ترتيب:</span>
                    <input v-model.number="ruleForm.sort_order" type="number" min="0" class="border rounded w-20 p-1 text-center" />
                  </div>
                  <div class="flex gap-2 mr-auto">
                    <button v-if="editingRuleId" type="button" class="bg-gray-500 text-white px-4 py-2 rounded-lg text-sm" @click="resetRuleForm">
                      إلغاء
                    </button>
                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-bold" :disabled="ruleForm.processing">
                      {{ editingRuleId ? '💾 تحديث' : '➕ إضافة قانون' }}
                    </button>
                  </div>
                </div>
              </form>
            </div>

            <div class="overflow-x-auto">
              <table class="w-full text-sm text-right border border-gray-200 rounded-lg overflow-hidden">
                <thead class="bg-gray-100">
                  <tr>
                    <th class="p-3">النوع</th>
                    <th class="p-3">الاسم / الشرط</th>
                    <th class="p-3">الخصم</th>
                    <th class="p-3">الحالة</th>
                    <th class="p-3">إجراءات</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="rule in rules" :key="rule.id" class="border-t">
                    <td class="p-3 text-xs text-gray-600">{{ rule.rule_type_label }}</td>
                    <td class="p-3">
                      <div class="font-medium">{{ rule.name || '—' }}</div>
                      <div class="text-xs text-gray-500">{{ rule.range_label }}</div>
                    </td>
                    <td class="p-3 font-bold text-red-600">{{ formatPrice(rule.deduction_amount) }}</td>
                    <td class="p-3">
                      <span :class="rule.is_active ? 'text-green-700' : 'text-gray-400'">
                        {{ rule.is_active ? 'نشط — يطبق على الجميع' : 'معطّل' }}
                      </span>
                    </td>
                    <td class="p-3 whitespace-nowrap">
                      <button type="button" class="text-blue-600 hover:underline text-xs ml-2" @click="editRule(rule)">تعديل</button>
                      <button type="button" class="text-red-600 hover:underline text-xs" @click="deleteRule(rule)">حذف</button>
                    </td>
                  </tr>
                  <tr v-if="!rules.length">
                    <td colspan="5" class="p-6 text-center text-gray-500">لا توجد قوانين بعد.</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';

export default {
  layout: AppLayout,
  components: { Link },
  props: {
    employees: { type: Array, default: () => [] },
    rules: { type: Array, default: () => [] },
  },
  data() {
    return {
      activeTab: 'schedules',
      scheduleRows: this.employees.map((e) => ({ ...e })),
      editingRuleId: null,
      scheduleForm: useForm({ schedules: [] }),
      ruleForm: useForm({
        name: '',
        rule_type: 'range',
        min_late_minutes: 15,
        max_late_minutes: 30,
        deduction_amount: 50,
        is_active: true,
        sort_order: 0,
      }),
    };
  },
  watch: {
    employees: {
      handler(v) {
        this.scheduleRows = (v || []).map((e) => ({ ...e }));
      },
      deep: true,
    },
  },
  methods: {
    formatPrice(amount) {
      return `${parseFloat(amount || 0).toFixed(2)} ج`;
    },
    saveSchedules() {
      this.scheduleForm.schedules = this.scheduleRows.map((row) => ({
        id: row.id,
        expected_checkin_time: row.expected_checkin_time || null,
        expected_checkout_time: row.expected_checkout_time || null,
        grace_minutes: row.grace_minutes ?? 0,
        late_deductions_enabled: !!row.late_deductions_enabled,
      }));
      this.scheduleForm.post(route('admin.employees.attendance-settings.schedules'), {
        preserveScroll: true,
      });
    },
    resetRuleForm() {
      this.editingRuleId = null;
      this.ruleForm.reset();
      this.ruleForm.rule_type = 'range';
      this.ruleForm.min_late_minutes = 15;
      this.ruleForm.max_late_minutes = 30;
      this.ruleForm.deduction_amount = 50;
      this.ruleForm.is_active = true;
      this.ruleForm.sort_order = 0;
    },
    editRule(rule) {
      this.editingRuleId = rule.id;
      this.ruleForm.name = rule.name || '';
      this.ruleForm.rule_type = rule.rule_type || 'range';
      this.ruleForm.min_late_minutes = rule.min_late_minutes;
      this.ruleForm.max_late_minutes = rule.max_late_minutes;
      this.ruleForm.deduction_amount = rule.deduction_amount;
      this.ruleForm.is_active = rule.is_active;
      this.ruleForm.sort_order = rule.sort_order;
      this.activeTab = 'rules';
      window.scrollTo({ top: 0, behavior: 'smooth' });
    },
    submitRule() {
      const payload = {
        name: this.ruleForm.name || null,
        rule_type: this.ruleForm.rule_type,
        min_late_minutes: this.ruleForm.min_late_minutes,
        max_late_minutes: this.ruleForm.rule_type === 'range' ? this.ruleForm.max_late_minutes : null,
        deduction_amount: this.ruleForm.deduction_amount,
        is_active: this.ruleForm.is_active,
        sort_order: this.ruleForm.sort_order,
      };
      if (this.editingRuleId) {
        router.put(route('admin.employees.attendance-settings.rules.update', this.editingRuleId), payload, {
          preserveScroll: true,
          onSuccess: () => this.resetRuleForm(),
        });
      } else {
        router.post(route('admin.employees.attendance-settings.rules.store'), payload, {
          preserveScroll: true,
          onSuccess: () => this.resetRuleForm(),
        });
      }
    },
    deleteRule(rule) {
      if (!confirm(`حذف قانون "${rule.name || rule.range_label}"؟`)) return;
      router.delete(route('admin.employees.attendance-settings.rules.destroy', rule.id), {
        preserveScroll: true,
      });
    },
  },
};
</script>
