<template>
  <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
    <h4 class="font-bold text-blue-900 mb-3">⏰ مواعيد الحضور والانصراف</h4>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div>
        <label class="block text-gray-700 text-sm font-bold mb-2">موعد الحضور الافتراضي</label>
        <input
          v-model="form.expected_checkin_time"
          type="time"
          class="w-full p-3 border border-gray-300 rounded-lg"
        />
      </div>
      <div>
        <label class="block text-gray-700 text-sm font-bold mb-2">موعد الانصراف الافتراضي</label>
        <input
          v-model="form.expected_checkout_time"
          type="time"
          class="w-full p-3 border border-gray-300 rounded-lg"
        />
      </div>
      <div>
        <label class="block text-gray-700 text-sm font-bold mb-2">فترة السماح الافتراضية (دقيقة)</label>
        <input
          v-model.number="form.grace_minutes"
          type="number"
          min="0"
          max="120"
          class="w-full p-3 border border-gray-300 rounded-lg"
        />
      </div>
    </div>

    <label class="flex items-center mt-4">
      <input
        v-model="form.use_weekly_schedule"
        type="checkbox"
        class="rounded border-gray-300 text-blue-600"
      />
      <span class="mr-2 text-sm font-medium text-gray-700">تخصيص مواعيد أو استثناء أيام معينة</span>
    </label>

    <div v-if="form.use_weekly_schedule" class="mt-4 overflow-x-auto">
      <p class="text-xs text-gray-600 mb-3">
        اترك مواعيد اليوم فارغة لاستخدام الموعد الافتراضي. أزل «يوم عمل» لاستثناء اليوم من المواعيد والخصومات.
      </p>
      <table class="min-w-full text-sm border border-blue-100 rounded-lg overflow-hidden bg-white">
        <thead class="bg-blue-100 text-blue-900">
          <tr>
            <th class="p-2 text-right">اليوم</th>
            <th class="p-2 text-center">يوم عمل</th>
            <th class="p-2 text-right">حضور</th>
            <th class="p-2 text-right">انصراف</th>
            <th class="p-2 text-right">سماح (د)</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="(day, index) in form.work_schedules"
            :key="day.day_of_week"
            class="border-t border-blue-50"
            :class="{ 'bg-gray-50 opacity-70': !day.is_working }"
          >
            <td class="p-2 font-medium text-gray-800">{{ day.label }}</td>
            <td class="p-2 text-center">
              <input
                v-model="day.is_working"
                type="checkbox"
                class="rounded border-gray-300 text-blue-600"
              />
            </td>
            <td class="p-2">
              <input
                v-model="day.expected_checkin_time"
                type="time"
                :disabled="!day.is_working"
                class="w-full p-2 border border-gray-300 rounded-lg disabled:bg-gray-100"
                :placeholder="form.expected_checkin_time || 'افتراضي'"
              />
            </td>
            <td class="p-2">
              <input
                v-model="day.expected_checkout_time"
                type="time"
                :disabled="!day.is_working"
                class="w-full p-2 border border-gray-300 rounded-lg disabled:bg-gray-100"
              />
            </td>
            <td class="p-2">
              <input
                v-model.number="day.grace_minutes"
                type="number"
                min="0"
                max="120"
                :disabled="!day.is_working"
                class="w-full p-2 border border-gray-300 rounded-lg disabled:bg-gray-100"
                placeholder="افتراضي"
              />
            </td>
          </tr>
        </tbody>
      </table>
      <div v-if="errors['work_schedules']" class="text-red-500 text-sm mt-2">{{ errors['work_schedules'] }}</div>
    </div>

    <label class="flex items-center mt-4">
      <input
        v-model="form.late_deductions_enabled"
        type="checkbox"
        class="rounded border-gray-300 text-blue-600"
      />
      <span class="mr-2 text-sm font-medium text-gray-700">تطبيق قوانين خصم التأخير</span>
    </label>
  </div>
</template>

<script>
export const defaultWorkSchedules = () => ([
  { day_of_week: 0, label: 'الأحد', is_working: true, expected_checkin_time: '', expected_checkout_time: '', grace_minutes: null },
  { day_of_week: 1, label: 'الإثنين', is_working: true, expected_checkin_time: '', expected_checkout_time: '', grace_minutes: null },
  { day_of_week: 2, label: 'الثلاثاء', is_working: true, expected_checkin_time: '', expected_checkout_time: '', grace_minutes: null },
  { day_of_week: 3, label: 'الأربعاء', is_working: true, expected_checkin_time: '', expected_checkout_time: '', grace_minutes: null },
  { day_of_week: 4, label: 'الخميس', is_working: true, expected_checkin_time: '', expected_checkout_time: '', grace_minutes: null },
  { day_of_week: 5, label: 'الجمعة', is_working: true, expected_checkin_time: '', expected_checkout_time: '', grace_minutes: null },
  { day_of_week: 6, label: 'السبت', is_working: true, expected_checkin_time: '', expected_checkout_time: '', grace_minutes: null },
]);

export default {
  name: 'EmployeeWorkScheduleFields',
  props: {
    form: {
      type: Object,
      required: true,
    },
    errors: {
      type: Object,
      default: () => ({}),
    },
  },
};
</script>
