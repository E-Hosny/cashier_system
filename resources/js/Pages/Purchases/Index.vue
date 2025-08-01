<script setup>
import { ref, computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const page = usePage();
const purchases = computed(() => page.props.purchases);
const rawMaterials = computed(() => page.props.rawMaterials || []);
const selectedDate = ref(page.props.selectedDate || '');
const from = ref(page.props.from || '');
const to = ref(page.props.to || '');
const today = new Date().toISOString().slice(0, 10);
const newPurchase = ref({
    description: '',
    quantity: '',
    purchase_unit: '',
    total_amount: '',
    purchase_date: today
});

// وحدة الشراء المتاحة للمادة المختارة
const filteredUnits = computed(() => {
    const material = rawMaterials.value.find(m => m.name === newPurchase.value.description);
    return material && material.purchase_unit ? [material.purchase_unit] : [];
});

// **حساب إجمالي المشتريات للفترة المحددة**
const totalAmount = computed(() => {
    return purchases.value.reduce((sum, purchase) => sum + Number(purchase.total_amount), 0);
});

// تحديث الفلتر حسب التاريخ أو الفترة
const filterPurchases = () => {
    // إذا تم اختيار يوم محدد، تجاهل الفترة
    const data = {
        date: selectedDate.value,
        from: selectedDate.value ? '' : from.value,
        to: selectedDate.value ? '' : to.value,
    };
    router.get('/purchases', data, { preserveState: true });
};

// إرسال بيانات المشتريات إلى الخادم
const submitPurchase = () => {
    if (!newPurchase.value.description || newPurchase.value.description.trim() === '') {
        alert('يرجى اختيار المادة الخام.');
        return;
    }
    router.post('/purchases', newPurchase.value, {
        onSuccess: () => {
            newPurchase.value = {
                description: '',
                quantity: '',
                total_amount: '',
                purchase_date: today
            };
        }
    });
};
</script>

<style>
/* Styles for responsive table */
@media (max-width: 640px) {
    .responsive-table thead {
        display: none;
    }
    .responsive-table tbody,
    .responsive-table tr,
    .responsive-table td {
        display: block;
        width: 100%;
    }
    .responsive-table tr {
        margin-bottom: 1rem;
        border: 1px solid #e5e7eb;
        border-radius: 0.5rem;
        overflow: hidden;
    }
    .responsive-table td {
        padding: 0.75rem 1rem;
        position: relative;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .responsive-table td:last-child {
        border-bottom: none;
    }
    .responsive-table td[data-label]::before {
        content: attr(data-label) ":";
        font-weight: bold;
        text-align: right;
        margin-left: 0.5rem;
    }
}
</style>

<template>
    <AppLayout title="المشتريات">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">إدارة المشتريات</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white shadow-xl rounded-lg p-6">
                    
                    <!-- فلترة المشتريات -->
                    <form @submit.prevent="filterPurchases" class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-6">
                        <div>
                            <label class="block text-gray-700 font-semibold">يوم محدد</label>
                            <input type="date" v-model="selectedDate" class="w-full border-gray-300 rounded-md shadow-sm">
                        </div>
                        <div>
                            <label class="block text-gray-700 font-semibold">من تاريخ</label>
                            <input type="date" v-model="from" class="w-full border-gray-300 rounded-md shadow-sm">
                        </div>
                        <div>
                            <label class="block text-gray-700 font-semibold">إلى تاريخ</label>
                            <input type="date" v-model="to" class="w-full border-gray-300 rounded-md shadow-sm">
                        </div>
                        <div class="flex items-end">
                            <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600 w-full">بحث</button>
                        </div>
                    </form>

                    <!-- نموذج إضافة المشتريات -->
                    <div class="mb-6 p-4 bg-gray-100 rounded-lg">
                        <h3 class="text-lg font-semibold text-gray-700 mb-2">إضافة مشتريات جديدة</h3>
                        <form @submit.prevent="submitPurchase">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-gray-700 font-semibold">اسم المنتج:</label>
                                    <select v-model="newPurchase.description" required class="w-full border-gray-300 rounded-md shadow-sm">
                                        <option value="" disabled>اختر المادة الخام</option>
                                        <option v-for="material in rawMaterials" :key="material.id" :value="material.name">
                                            {{ material.name }} <span v-if="material.unit">({{ material.unit }})</span>
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-gray-700 font-semibold">الكمية:</label>
                                    <div class="flex gap-2">
                                        <input v-model="newPurchase.quantity" type="number" step="0.01" min="0.01"
                                            class="w-full border-gray-300 rounded-md shadow-sm"
                                            placeholder="مثال: 0.5 أو 1.25">
                                        <select v-model="newPurchase.purchase_unit" class="border-gray-300 rounded-md shadow-sm">
                                            <option v-for="unit in filteredUnits" :key="unit" :value="unit">
                                                {{ unit }}
                                            </option>
                                            <option v-if="filteredUnits.length === 0" disabled value="">وحدة</option>
                                        </select>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-gray-700 font-semibold">المبلغ الإجمالي:</label>
                                    <input v-model="newPurchase.total_amount" type="number" required
                                        class="w-full border-gray-300 rounded-md shadow-sm">
                                </div>
                                <div>
                                    <label class="block text-gray-700 font-semibold">تاريخ الشراء:</label>
                                    <input v-model="newPurchase.purchase_date" type="date" required
                                        class="w-full border-gray-300 rounded-md shadow-sm">
                                </div>
                            </div>
                            <button type="submit"
                                class="mt-4 bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600">
                                إضافة
                            </button>
                        </form>
                    </div>

                    <!-- عرض المشتريات -->
                    <h3 class="text-lg font-semibold text-gray-700 mb-4">قائمة المشتريات لليوم</h3>
                    <div class="overflow-x-auto">
                      <table class="w-full border-collapse border border-gray-200 responsive-table">
                          <thead class="bg-gray-100">
                              <tr class="bg-gray-100">
                                  <th class="border border-gray-200 p-2">المبلغ الإجمالي</th>
                                  <th class="border border-gray-200 p-2">الكمية</th>
                                  <th class="border border-gray-200 p-2">المنتج</th>
                                  <th class="border border-gray-200 p-2">التاريخ</th>
                              </tr>
                          </thead>
                          <tbody>
                              <tr v-if="purchases.length === 0">
                                  <td colspan="4" class="text-center p-6 text-gray-500">
                                      لا توجد مشتريات لهذا اليوم.
                                  </td>
                              </tr>
                              <tr v-for="purchase in purchases" :key="purchase.id">
                                  <td class="border border-gray-200 p-2 text-center" data-label="المبلغ الإجمالي">{{ purchase.total_amount }}</td>
                                  <td class="border border-gray-200 p-2 text-center" data-label="الكمية">{{ purchase.quantity ?? 'غير محدد' }}</td>
                                  <td class="border border-gray-200 p-2 text-center" data-label="المنتج">{{ purchase.product_name }}</td>
                                  <td class="border border-gray-200 p-2 text-center" data-label="التاريخ">{{ purchase.purchase_date }}</td>
                              </tr>
                          </tbody>
                      </table>
                    </div>

                    <!-- ✅ تصميم بارز لإجمالي المشتريات -->
                    <div class="mt-6 p-4 bg-green-300 text-gray-900 rounded-lg shadow-md text-center">
                        <h3 class="text-xl font-bold">إجمالي المشتريات لليوم:</h3>
                        <p class="text-2xl mt-2">{{ totalAmount }} </p>
                    </div>


                </div>
            </div>
        </div>
    </AppLayout>
</template>
