<script setup>
import { ref, computed, watch } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const page = usePage();
const purchases = computed(() => page.props.purchases);
const rawMaterials = computed(() => page.props.rawMaterials || []);
const customPurchaseItems = computed(() => page.props.customPurchaseItems || []);
const selectedDate = ref(page.props.selectedDate || '');
const from = ref(page.props.from || '');
const to = ref(page.props.to || '');
const today = new Date().toISOString().slice(0, 10);

const newCustomItem = ref({ name: '', unit: '' });
const showCustomPanel = ref(false);

const emptyPurchase = () => ({
    purchase_kind: 'raw',
    description: '',
    custom_purchase_item_id: '',
    quantity: '',
    purchase_unit: '',
    total_amount: '',
    purchase_date: today,
});

const newPurchase = ref(emptyPurchase());

const isRawPurchase = computed(() => newPurchase.value.purchase_kind === 'raw');

const selectedMaterial = computed(() =>
    rawMaterials.value.find((m) => m.name === newPurchase.value.description) ?? null
);

const selectedCustomItem = computed(() =>
    customPurchaseItems.value.find(
        (item) => String(item.id) === String(newPurchase.value.custom_purchase_item_id)
    ) ?? null
);

const filteredUnits = computed(() => {
    const material = selectedMaterial.value;
    return material?.purchase_unit ? [material.purchase_unit] : [];
});

const selectedMaterialUnitsPerPieceLabel = computed(() => {
    const m = selectedMaterial.value;
    if (!m) return '';

    if (m.quantity_per_unit) {
        const q = parseFloat(m.quantity_per_unit);
        const qStr = Number.isNaN(q) ? m.quantity_per_unit : (q % 1 === 0 ? q : q.toFixed(2));
        const pieceUnit = m.unit || 'قطعة';
        const consumeUnit = m.consume_unit || 'وحدة الاستهلاك';
        return `كل ${pieceUnit} = ${qStr} ${consumeUnit}`;
    }

    return 'لم يُحدد عدد وحدات القطعة لهذه المادة';
});

watch(
    () => newPurchase.value.purchase_kind,
    () => {
        newPurchase.value.description = '';
        newPurchase.value.custom_purchase_item_id = '';
        newPurchase.value.purchase_unit = '';
    }
);

watch(
    () => newPurchase.value.description,
    (name) => {
        if (!isRawPurchase.value) return;
        const material = rawMaterials.value.find((m) => m.name === name);
        newPurchase.value.purchase_unit = material?.purchase_unit ?? '';
    }
);

const totalAmount = computed(() => {
    return purchases.value.reduce((sum, purchase) => sum + Number(purchase.total_amount), 0);
});

const filterPurchases = () => {
    const data = {
        date: selectedDate.value,
        from: selectedDate.value ? '' : from.value,
        to: selectedDate.value ? '' : to.value,
    };
    router.get('/purchases', data, { preserveState: true });
};

const submitCustomItem = () => {
    if (!newCustomItem.value.name.trim()) {
        alert('يرجى إدخال اسم البند المخصص.');
        return;
    }
    router.post('/purchases/custom-items', newCustomItem.value, {
        onSuccess: () => {
            newCustomItem.value = { name: '', unit: '' };
        },
        preserveScroll: true,
    });
};

const deleteCustomItem = (id) => {
    if (!confirm('حذف هذا البند من قائمة المشتريات المخصصة؟')) return;
    router.delete(`/purchases/custom-items/${id}`);
};

const submitPurchase = () => {
    if (isRawPurchase.value) {
        if (!newPurchase.value.description?.trim()) {
            alert('يرجى اختيار المادة الخام.');
            return;
        }
    } else if (!newPurchase.value.custom_purchase_item_id) {
        alert('يرجى اختيار بند من المشتريات المخصصة.');
        return;
    }

    router.post('/purchases', newPurchase.value, {
        onSuccess: () => {
            newPurchase.value = emptyPurchase();
        },
    });
};

const purchaseKindLabel = (purchase) =>
    purchase.purchase_kind === 'custom' ? 'مخصص' : 'مادة خام';
</script>

<style>
.custom-panel-enter-active .custom-panel-aside,
.custom-panel-leave-active .custom-panel-aside {
    transition: transform 0.25s ease;
}
.custom-panel-enter-from .custom-panel-aside,
.custom-panel-leave-to .custom-panel-aside {
    transform: translateX(-100%);
}
.custom-panel-enter-active .custom-panel-backdrop,
.custom-panel-leave-active .custom-panel-backdrop {
    transition: opacity 0.25s ease;
}
.custom-panel-enter-from .custom-panel-backdrop,
.custom-panel-leave-to .custom-panel-backdrop {
    opacity: 0;
}

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

        <div class="py-12" dir="rtl">
            <Teleport to="body">
                <Transition name="custom-panel">
                    <div v-if="showCustomPanel" class="fixed inset-0 z-50" dir="rtl">
                        <div
                            class="custom-panel-backdrop absolute inset-0 bg-black/40"
                            @click="showCustomPanel = false"
                        />
                        <aside
                            class="custom-panel-aside absolute inset-y-0 left-0 w-full max-w-md h-full bg-white shadow-2xl flex flex-col overflow-hidden"
                            role="dialog"
                            aria-labelledby="custom-items-panel-title"
                        >
                            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 bg-amber-50">
                                <h3 id="custom-items-panel-title" class="text-lg font-semibold text-gray-800">
                                    بنود المشتريات المخصصة
                                </h3>
                                <button
                                    type="button"
                                    class="p-2 rounded-lg text-gray-600 hover:bg-amber-100 hover:text-gray-900"
                                    aria-label="إغلاق"
                                    @click="showCustomPanel = false"
                                >
                                    ✕
                                </button>
                            </div>
                            <div class="flex-1 overflow-y-auto p-4">
                                <p class="text-sm text-gray-600 mb-4">
                                    أصناف شراء غير مرتبطة بمواد خام. اخترها عند تسجيل «شراء مخصص».
                                </p>
                                <form @submit.prevent="submitCustomItem" class="space-y-3 mb-4">
                                    <div>
                                        <label class="block text-gray-700 font-semibold text-sm mb-1">اسم البند</label>
                                        <input v-model="newCustomItem.name" type="text" required
                                            class="w-full border-gray-300 rounded-md shadow-sm"
                                            placeholder="مثال: صيانة ماكينة">
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 font-semibold text-sm mb-1">الوحدة (اختياري)</label>
                                        <input v-model="newCustomItem.unit" type="text"
                                            class="w-full border-gray-300 rounded-md shadow-sm"
                                            placeholder="مثال: مرة، كرتونة">
                                    </div>
                                    <button type="submit"
                                        class="w-full bg-amber-600 text-white px-4 py-2 rounded-md hover:bg-amber-700">
                                        إضافة للقائمة
                                    </button>
                                </form>
                                <ul v-if="customPurchaseItems.length"
                                    class="divide-y divide-gray-200 border border-gray-200 rounded-md">
                                    <li v-for="item in customPurchaseItems" :key="item.id"
                                        class="flex justify-between items-center px-3 py-2.5 text-sm">
                                        <span>
                                            {{ item.name }}
                                            <span v-if="item.unit" class="text-gray-500">({{ item.unit }})</span>
                                        </span>
                                        <button type="button"
                                            class="text-red-600 hover:text-red-800 text-xs font-semibold shrink-0 mr-2"
                                            @click="deleteCustomItem(item.id)">
                                            حذف
                                        </button>
                                    </li>
                                </ul>
                                <p v-else class="text-sm text-gray-500 text-center py-6">لا توجد بنود مخصصة بعد.</p>
                            </div>
                        </aside>
                    </div>
                </Transition>
            </Teleport>

            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white shadow-xl rounded-lg p-6">

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
                        <div class="flex flex-col gap-2 justify-end">
                            <button
                                type="button"
                                class="w-full bg-amber-600 text-white px-4 py-2 rounded-md hover:bg-amber-700 font-semibold text-sm"
                                @click="showCustomPanel = true"
                            >
                                بنود المشتريات المخصصة
                                <span v-if="customPurchaseItems.length" class="mr-1 opacity-90">
                                    ({{ customPurchaseItems.length }})
                                </span>
                            </button>
                            <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600 w-full">
                                بحث
                            </button>
                        </div>
                    </form>

                    <!-- نموذج إضافة المشتريات -->
                    <div class="mb-6 p-4 bg-gray-100 rounded-lg">
                        <h3 class="text-lg font-semibold text-gray-700 mb-2">إضافة مشتريات جديدة</h3>
                        <form @submit.prevent="submitPurchase">
                            <div class="mb-4 flex flex-wrap gap-4">
                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                    <input v-model="newPurchase.purchase_kind" type="radio" value="raw"
                                        class="text-blue-600 border-gray-300 focus:ring-blue-500" />
                                    <span class="font-semibold text-gray-800">مادة خام</span>
                                </label>
                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                    <input v-model="newPurchase.purchase_kind" type="radio" value="custom"
                                        class="text-blue-600 border-gray-300 focus:ring-blue-500" />
                                    <span class="font-semibold text-gray-800">شراء مخصص</span>
                                </label>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div v-if="isRawPurchase">
                                    <label class="block text-gray-700 font-semibold">المادة الخام:</label>
                                    <select v-model="newPurchase.description" required
                                        class="w-full border-gray-300 rounded-md shadow-sm">
                                        <option value="" disabled>اختر المادة الخام</option>
                                        <option v-for="material in rawMaterials" :key="material.id" :value="material.name">
                                            {{ material.name }}<template v-if="material.unit"> ({{ material.unit }})</template>
                                        </option>
                                    </select>
                                    <p v-if="selectedMaterial" class="mt-2 text-sm text-gray-700 bg-white border border-gray-200 rounded-md px-3 py-2">
                                        <span class="font-semibold text-gray-800">عدد وحدات القطعة:</span>
                                        {{ selectedMaterialUnitsPerPieceLabel }}
                                    </p>
                                </div>
                                <div v-else>
                                    <label class="block text-gray-700 font-semibold">البند المخصص:</label>
                                    <select v-model="newPurchase.custom_purchase_item_id" required
                                        class="w-full border-gray-300 rounded-md shadow-sm">
                                        <option value="" disabled>اختر بنداً مخصصاً</option>
                                        <option v-for="item in customPurchaseItems" :key="item.id" :value="item.id">
                                            {{ item.name }}<template v-if="item.unit"> ({{ item.unit }})</template>
                                        </option>
                                    </select>
                                    <p v-if="customPurchaseItems.length === 0" class="mt-2 text-sm text-amber-700">
                                        لا توجد بنود بعد.
                                        <button type="button" class="underline font-semibold mr-1" @click="showCustomPanel = true">
                                            أضف بنوداً مخصصة
                                        </button>
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-gray-700 font-semibold">
                                        <template v-if="isRawPurchase">
                                            {{ selectedMaterial?.quantity_per_unit ? 'عدد القطع:' : 'الكمية:' }}
                                        </template>
                                        <template v-else>الكمية (اختياري):</template>
                                    </label>
                                    <div class="flex gap-2">
                                        <input v-model="newPurchase.quantity" type="number" step="0.01" min="0.01"
                                            :required="isRawPurchase"
                                            class="w-full border-gray-300 rounded-md shadow-sm">
                                        <select v-if="isRawPurchase" v-model="newPurchase.purchase_unit"
                                            class="border-gray-300 rounded-md shadow-sm">
                                            <option v-for="unit in filteredUnits" :key="unit" :value="unit">
                                                {{ unit }}
                                            </option>
                                            <option v-if="filteredUnits.length === 0" disabled value="">وحدة</option>
                                        </select>
                                        <span v-else-if="selectedCustomItem?.unit"
                                            class="flex items-center px-3 text-sm text-gray-600 border border-gray-200 rounded-md bg-white whitespace-nowrap">
                                            {{ selectedCustomItem.unit }}
                                        </span>
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

                    <div class="mb-4 text-sm text-blue-600 bg-blue-50 p-3 rounded-lg">
                        ℹ️ بشكل افتراضي تُعرض مشتريات يوم العمل الحالي (من الساعة 7:00 صباحاً إلى 7:00 صباحاً اليوم التالي). استخدم الفلاتر أعلاه لعرض يوم أو فترة محددة.
                    </div>

                    <h3 class="text-lg font-semibold text-gray-700 mb-4">قائمة المشتريات لليوم</h3>
                    <div class="overflow-x-auto">
                      <table class="w-full border-collapse border border-gray-200 responsive-table">
                          <thead class="bg-gray-100">
                              <tr class="bg-gray-100">
                                  <th class="border border-gray-200 p-2">المبلغ الإجمالي</th>
                                  <th class="border border-gray-200 p-2">الكمية</th>
                                  <th class="border border-gray-200 p-2">المنتج</th>
                                  <th class="border border-gray-200 p-2">النوع</th>
                                  <th class="border border-gray-200 p-2">التاريخ</th>
                              </tr>
                          </thead>
                          <tbody>
                              <tr v-if="purchases.length === 0">
                                  <td colspan="5" class="text-center p-6 text-gray-500">
                                      لا توجد مشتريات لهذا اليوم.
                                  </td>
                              </tr>
                              <tr v-for="purchase in purchases" :key="purchase.id">
                                  <td class="border border-gray-200 p-2 text-center" data-label="المبلغ الإجمالي">{{ purchase.total_amount }}</td>
                                  <td class="border border-gray-200 p-2 text-center" data-label="الكمية">{{ purchase.quantity ?? '—' }}</td>
                                  <td class="border border-gray-200 p-2 text-center" data-label="المنتج">{{ purchase.description }}</td>
                                  <td class="border border-gray-200 p-2 text-center" data-label="النوع">
                                      <span
                                        class="inline-block px-2 py-0.5 rounded text-xs font-semibold"
                                        :class="purchase.purchase_kind === 'custom' ? 'bg-amber-100 text-amber-900' : 'bg-emerald-100 text-emerald-900'"
                                      >
                                        {{ purchaseKindLabel(purchase) }}
                                      </span>
                                  </td>
                                  <td class="border border-gray-200 p-2 text-center" data-label="التاريخ">{{ purchase.purchase_date }}</td>
                              </tr>
                          </tbody>
                      </table>
                    </div>

                    <div class="mt-6 p-4 bg-green-300 text-gray-900 rounded-lg shadow-md text-center">
                        <h3 class="text-xl font-bold">إجمالي المشتريات لليوم:</h3>
                        <p class="text-2xl mt-2">{{ totalAmount }} </p>
                    </div>

                </div>
            </div>
        </div>
    </AppLayout>
</template>
