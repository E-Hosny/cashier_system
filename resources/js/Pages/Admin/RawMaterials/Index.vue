<template>
  <div class="raw-materials-page mx-auto max-w-screen-2xl w-full p-4 sm:p-6" dir="rtl">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8 gap-4 no-print">
      <h1 class="text-3xl font-bold text-gray-800">🛢️ إدارة المواد الخام</h1>
      <div v-if="isCentralView" class="flex flex-wrap gap-2">
        <a v-if="canManageRawCategories" :href="route('admin.raw-material-categories.index')" class="btn-gray">📁 فئات المواد الخام</a>
        <a v-if="canAddRaw" :href="route('admin.raw-materials.create')" class="btn-primary">➕ إضافة مادة خام</a>
      </div>
    </div>

    <div class="mb-4 flex flex-wrap gap-2 no-print">
      <button
        type="button"
        class="px-4 py-2 rounded-lg font-semibold transition"
        :class="pageTab === 'materials' ? 'bg-slate-700 text-white' : 'bg-white border border-slate-300 text-gray-700'"
        @click="setPageTab('materials')"
      >
        🛢️ المواد الخام
      </button>
      <button
        type="button"
        class="px-4 py-2 rounded-lg font-semibold transition"
        :class="pageTab === 'fridge' ? 'bg-cyan-700 text-white' : 'bg-white border border-cyan-300 text-cyan-800'"
        @click="setPageTab('fridge')"
      >
        🧊 التلاجة
      </button>
      <a
        v-if="canReceive && !isCentralView"
        :href="route('admin.fridge.pull')"
        class="px-4 py-2 rounded-lg font-semibold bg-cyan-100 text-cyan-900 border border-cyan-300 hover:bg-cyan-200 mr-auto"
      >
        سحب للتلاجة (الفرع)
      </a>
    </div>

    <FridgePanel
      v-if="pageTab === 'fridge'"
      :fridge="fridge"
      :is-central-view="isCentralView"
      :can-manage="canManageFridge"
      :view-scope="viewScopeSelect"
    />

    <template v-else-if="pageTab === 'materials'">

    <div class="mb-6 bg-slate-50 border border-slate-200 rounded-xl p-4 flex flex-wrap items-center gap-4 no-print">
      <span class="text-gray-800 font-semibold whitespace-nowrap">عرض المخزون:</span>
      <select
        v-model="viewScopeSelect"
        class="border border-gray-300 rounded-lg p-2.5 min-w-[220px] bg-white font-medium"
        @change="applyViewScope"
      >
        <option value="central">🏢 المخزون المركزي</option>
        <option v-for="b in hubBranches" :key="b.id" :value="String(b.id)">📍 {{ b.name }}</option>
      </select>
      <p v-if="isCentralView" class="text-sm text-gray-600">إدارة المواد، التكويد، والطباعة من المركز.</p>
      <p v-else class="text-sm text-gray-600">مخزون وسحوبات يوم العمل للفرع المحدد فقط.</p>
    </div>

    <template v-if="isCentralView">
    <div v-if="showTableTools" class="mb-4 flex flex-wrap items-center gap-3 no-print">
      <label class="text-gray-700 font-medium whitespace-nowrap">تصفية حسب الفئة:</label>
      <select
        v-model="filterCategoryId"
        class="border border-gray-300 rounded-lg p-2 min-w-[200px]"
        @change="applyFilters"
      >
        <option value="">الكل</option>
        <option v-for="c in rawMaterialCategories" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
      </select>
    </div>

    <!-- Mobile cards (best UX on phones) -->
    <div v-if="!isCashier" class="sm:hidden space-y-3 no-print">
      <div
        v-for="material in rawMaterialsLocal"
        :key="material.id"
        class="shadow rounded-xl border p-4"
        :class="isStockLow(material) ? 'border-red-200 bg-red-100' : 'border-gray-200 bg-white'"
      >
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <div class="text-lg font-bold text-gray-800 truncate">{{ material.name }}</div>
            <div class="text-sm text-gray-600 mt-1">
              <span class="font-medium">الفئة:</span>
              <span>{{ material.category?.name || '—' }}</span>
            </div>
          </div>
          <div class="text-sm text-gray-600 whitespace-nowrap">
            <span class="font-medium">حد التنبيه:</span>
            <span>{{ formatAlertThreshold(material) }}</span>
          </div>
        </div>

        <div class="mt-3 grid grid-cols-2 gap-3 text-sm">
          <div class="bg-gray-50 rounded-lg p-3 border border-gray-200">
            <div class="text-gray-600 mb-1">عدد وحدات القطعة</div>
            <div class="font-semibold text-gray-800">
              {{ formatQuantityPerUnit(material) }}
              <span v-if="material.consume_unit" class="text-gray-500 font-normal">{{ material.consume_unit }}</span>
            </div>
          </div>

          <div class="bg-gray-50 rounded-lg p-3 border border-gray-200">
            <div class="text-gray-600 mb-1">سعر المادة الخام</div>
            <div class="font-semibold text-blue-700">
              <template v-if="material.purchase_price != null">
                {{ material.purchase_price }} جنيه
                <span v-if="material.unit" class="text-gray-500 font-normal">/ {{ material.unit }}</span>
              </template>
              <template v-else>لم يتم تحديد السعر</template>
            </div>
          </div>
        </div>

        <div class="mt-3 bg-white rounded-lg p-3 border border-gray-200">
          <div class="text-gray-600 mb-1 text-sm">الكمية الحالية (المخزون المركزي)</div>
          <div class="font-mono font-bold text-gray-900">
            <template v-if="material.quantity_per_unit">
              {{ formatStockUnits(material) }} {{ material.unit }}
              <span class="text-gray-600 font-normal">({{ formatStockConsume(material) }} {{ material.consume_unit }})</span>
              <span v-if="material.pending_pieces > 0" class="block text-amber-800 text-sm font-semibold mt-1">
                تم التكويد: {{ formatPendingPieces(material) }} {{ material.unit }}
              </span>
            </template>
            <template v-else>
              {{ material.stock }} {{ material.consume_unit }}
              <span v-if="material.purchase_unit && material.consume_unit && material.stock" class="text-gray-600 font-normal">
                ({{ (material.stock / ((material.purchase_unit === 'لتر' && material.consume_unit === 'مللي') ? 1000 : (material.purchase_unit === 'كجم' && material.consume_unit === 'جرام') ? 1000 : 1)).toFixed(2) }} {{ material.purchase_unit }})
              </span>
              <span v-if="material.pending_pieces > 0" class="block text-amber-800 text-sm font-semibold mt-1">
                تم التكويد: {{ formatPendingPieces(material) }} {{ material.unit }}
              </span>
            </template>
          </div>
        </div>

        <div class="mt-3 bg-gray-50 rounded-lg p-3 border border-gray-200">
          <div class="text-gray-600 mb-1 text-sm">معلومات التسعير</div>
          <div v-if="material.unit_consume_price" class="text-sm">
            <div class="font-semibold text-green-700">{{ material.unit_consume_price }} جنيه / {{ material.consume_unit }}</div>
            <div class="text-xs text-gray-600 mt-1">سعر وحدة الاستهلاك محسوب تلقائياً</div>
          </div>
          <div v-else class="text-gray-500 text-sm">لم يتم تحديد السعر</div>
        </div>

        <div class="mt-4 flex flex-wrap gap-2 justify-end">
          <button v-if="canPrint" type="button" @click="openPrintModal(material)" class="btn-blue-outline">🏷️ طباعة كود</button>
          <a v-if="canEdit" :href="route('admin.raw-materials.edit', material.id)" class="btn-yellow">✏️ تعديل</a>
          <button v-if="canDelete" @click="deleteMaterial(material.id)" class="btn-red">🗑️ حذف</button>
        </div>
      </div>
    </div>

    <!-- Desktop table -->
    <div v-if="!isCashier" class="hidden sm:block bg-white shadow-lg rounded-xl overflow-x-auto no-print">
      <table class="w-full min-w-[1180px] text-end">
        <thead class="bg-gray-200 hidden sm:table-header-group">
          <tr>
            <th class="p-4">اسم المادة</th>
            <th class="p-4">الفئة</th>
            <th class="p-4">عدد وحدات القطعة</th>
            <th class="p-4 min-w-[320px]">الكمية الحالية (المخزون المركزي)</th>
            <th class="p-4">سعر المادة الخام</th>
            <th class="p-4">معلومات التسعير</th>
            <th class="p-4">حد التنبيه</th>
            <th class="p-4 text-center">الإجراءات</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="material in rawMaterialsLocal" :key="material.id" class="block sm:table-row border-t sm:border-t-0 border-gray-200 hover:bg-gray-50" :class="{'bg-red-100 hover:bg-red-200': isStockLow(material)}">
            <td class="p-4 block sm:table-cell" data-label="اسم المادة">{{ material.name }}</td>
            <td class="p-4 block sm:table-cell text-gray-700" data-label="الفئة">{{ material.category?.name || '—' }}</td>
            <td class="p-4 block sm:table-cell text-gray-700" data-label="عدد وحدات القطعة">
              {{ formatQuantityPerUnit(material) }}
              <span v-if="material.consume_unit" class="text-gray-500">{{ material.consume_unit }}</span>
            </td>
            <td class="p-4 block sm:table-cell font-mono font-bold min-w-[320px]" data-label="الكمية الحالية (المخزون المركزي)">
              <template v-if="material.quantity_per_unit">
                {{ formatStockUnits(material) }} {{ material.unit }}
                <span class="text-gray-600 font-normal">({{ formatStockConsume(material) }} {{ material.consume_unit }})</span>
                <span v-if="material.pending_pieces > 0" class="block text-amber-800 text-sm font-semibold mt-1">
                  تم التكويد: {{ formatPendingPieces(material) }} {{ material.unit }}
                </span>
              </template>
              <template v-else>
                {{ material.stock }} {{ material.consume_unit }}
                <span v-if="material.purchase_unit && material.consume_unit && material.stock" class="text-gray-600 font-normal">
                  ({{ (material.stock / ((material.purchase_unit === 'لتر' && material.consume_unit === 'مللي') ? 1000 : (material.purchase_unit === 'كجم' && material.consume_unit === 'جرام') ? 1000 : 1)).toFixed(2) }} {{ material.purchase_unit }})
                </span>
                <span v-if="material.pending_pieces > 0" class="block text-amber-800 text-sm font-semibold mt-1">
                  تم التكويد: {{ formatPendingPieces(material) }} {{ material.unit }}
                </span>
              </template>
            </td>
            <td class="p-4 block sm:table-cell" data-label="سعر المادة الخام">
              <div v-if="material.purchase_price != null" class="font-semibold text-blue-700">
                {{ material.purchase_price }} جنيه
                <span v-if="material.unit" class="text-gray-500 font-normal">/ {{ material.unit }}</span>
              </div>
              <div v-else class="text-gray-500">لم يتم تحديد السعر</div>
            </td>
            <td class="p-4 block sm:table-cell" data-label="معلومات التسعير">
              <div v-if="material.unit_consume_price" class="text-sm">
                <div class="font-semibold text-green-700">{{ material.unit_consume_price }} جنيه / {{ material.consume_unit }}</div>
                <div class="text-xs text-gray-600 mt-1">سعر وحدة الاستهلاك محسوب تلقائياً</div>
              </div>
              <div v-else class="text-gray-500 text-sm">لم يتم تحديد السعر</div>
            </td>
            <td class="p-4 block sm:table-cell" data-label="حد التنبيه">{{ formatAlertThreshold(material) }}</td>
            <td class="p-4 block sm:table-cell" data-label="الإجراءات">
              <div class="flex flex-nowrap justify-center items-center gap-2 whitespace-nowrap">
                <button v-if="canPrint" type="button" @click="openPrintModal(material)" class="btn-blue-outline">🏷️ طباعة كود</button>
                <a v-if="canEdit" :href="route('admin.raw-materials.edit', material.id)" class="btn-yellow">✏️ تعديل</a>
                <button v-if="canDelete" @click="deleteMaterial(material.id)" class="btn-red">🗑️ حذف</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    </template>

    <template v-else-if="branchDetail">
      <div class="mb-4 flex flex-wrap items-center justify-between gap-3 no-print">
        <div>
          <h2 class="text-xl font-bold text-gray-800">فرع: {{ branchDetail.branch_name }}</h2>
          <p class="text-sm text-gray-600 mt-1">مخزون المواد الخام وسحوبات يوم العمل لهذا الفرع.</p>
          <p v-if="branchDetail.can_edit_stock" class="text-sm text-amber-800 mt-1 font-medium">
            كسوبر أدمن: عدّل مخزون الفرع بالقطع أو بالوحدة الاستهلاكية — يُحدَّث الحقل الآخر تلقائياً.
          </p>
        </div>
        <div v-if="showTableTools" class="flex flex-wrap items-center gap-2">
          <label class="text-gray-700 font-medium whitespace-nowrap">الفئة:</label>
          <select
            v-model="filterCategoryId"
            class="border border-gray-300 rounded-lg p-2 min-w-[180px]"
            @change="applyFilters"
          >
            <option value="">الكل</option>
            <option v-for="c in rawMaterialCategories" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
          </select>
        </div>
      </div>

      <!-- جوال: كارت لكل مادة -->
      <div class="sm:hidden space-y-3 mb-8 no-print">
        <p
          v-if="!branchDetail.materials?.length"
          class="text-center text-gray-500 p-6 bg-white rounded-xl shadow border border-gray-200"
        >
          لا توجد مواد خام في هذا العرض.
        </p>
        <div
          v-for="m in branchDetail.materials"
          :key="'branch-card-' + m.id"
          class="shadow rounded-xl border p-4"
          :class="m.is_low ? 'border-red-200 bg-red-50' : 'border-gray-200 bg-white'"
        >
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <div class="text-lg font-bold text-gray-800">{{ m.name }}</div>
              <div class="text-sm text-gray-600 mt-1">
                <span class="font-medium">الفئة:</span>
                {{ m.category?.name || '—' }}
              </div>
            </div>
            <div v-if="m.is_low" class="text-xs font-bold text-red-700 bg-red-100 px-2 py-1 rounded shrink-0">
              مخزون منخفض
            </div>
          </div>

          <div class="mt-3 grid grid-cols-2 gap-3 text-sm">
            <div class="bg-gray-50 rounded-lg p-3 border border-gray-200">
              <div class="text-gray-600 mb-1">مخزون الفرع (قطع)</div>
              <template v-if="branchStockEdit.productId === m.id">
                <input
                  :value="branchStockEdit.pieces"
                  type="number"
                  min="0"
                  step="any"
                  class="w-full border border-amber-400 rounded p-2 text-center font-mono font-bold"
                  @input="onBranchPiecesInput(m, $event)"
                />
                <span class="text-gray-500 text-xs mt-1 block text-center">{{ m.unit }}</span>
              </template>
              <div v-else class="font-mono font-bold text-gray-900">
                {{ m.branch_stock_pieces }} {{ m.unit }}
              </div>
            </div>
            <div class="bg-gray-50 rounded-lg p-3 border border-gray-200">
              <div class="text-gray-600 mb-1">بالوحدة الاستهلاكية</div>
              <template v-if="branchStockEdit.productId === m.id">
                <input
                  :value="branchStockEdit.consume"
                  type="number"
                  min="0"
                  step="any"
                  class="w-full border border-amber-400 rounded p-2 text-center font-mono font-bold"
                  @input="onBranchConsumeInput(m, $event)"
                />
                <span class="text-gray-500 text-xs mt-1 block text-center">{{ m.consume_unit }}</span>
              </template>
              <div v-else class="font-mono font-bold text-gray-800">
                {{ m.branch_stock_consume }} {{ m.consume_unit }}
              </div>
            </div>
          </div>

          <div class="mt-3 text-sm text-gray-600">
            <span class="font-medium">حد التنبيه:</span>
            {{ m.alert_pieces != null ? m.alert_pieces + ' ' + (m.unit || '') : '—' }}
          </div>

          <div v-if="branchDetail.can_edit_stock" class="mt-4 flex flex-wrap gap-2 justify-end">
            <template v-if="branchStockEdit.productId === m.id">
              <button type="button" class="btn-primary text-sm py-2 px-3" @click="saveBranchStock(m)">حفظ</button>
              <button type="button" class="btn-gray text-sm py-2 px-3" @click="cancelBranchStockEdit">إلغاء</button>
            </template>
            <button v-else type="button" class="btn-yellow text-sm py-2 px-3" @click="startBranchStockEdit(m)">تعديل المخزون</button>
          </div>
        </div>
      </div>

      <!-- سطح المكتب: جدول -->
      <div class="hidden sm:block bg-white shadow-lg rounded-xl overflow-x-auto mb-8 no-print">
        <table class="w-full min-w-[900px] text-end text-sm">
          <thead class="bg-gray-200">
            <tr>
              <th class="p-3">المادة</th>
              <th class="p-3">الفئة</th>
              <th class="p-3">مخزون الفرع (قطع)</th>
              <th class="p-3">بالوحدة الاستهلاكية</th>
              <th class="p-3">حد التنبيه</th>
              <th v-if="branchDetail.can_edit_stock" class="p-3 text-center">تعديل المخزون</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!branchDetail.materials?.length">
              <td :colspan="branchDetail.can_edit_stock ? 6 : 5" class="p-6 text-center text-gray-500">لا توجد مواد خام في هذا العرض.</td>
            </tr>
            <tr
              v-for="m in branchDetail.materials"
              :key="m.id"
              :class="m.is_low ? 'bg-red-50' : ''"
            >
              <td class="p-3 font-semibold">{{ m.name }}</td>
              <td class="p-3">{{ m.category?.name || '—' }}</td>
              <td class="p-3 font-mono font-bold">
                <template v-if="branchStockEdit.productId === m.id">
                  <input
                    :value="branchStockEdit.pieces"
                    type="number"
                    min="0"
                    step="any"
                    class="w-24 border border-amber-400 rounded p-1 text-center"
                    @input="onBranchPiecesInput(m, $event)"
                  />
                  <span class="text-gray-600 font-normal text-xs mr-1">{{ m.unit }}</span>
                </template>
                <template v-else>{{ m.branch_stock_pieces }} {{ m.unit }}</template>
              </td>
              <td class="p-3 text-gray-600">
                <template v-if="branchStockEdit.productId === m.id">
                  <input
                    :value="branchStockEdit.consume"
                    type="number"
                    min="0"
                    step="any"
                    class="w-24 border border-amber-400 rounded p-1 text-center font-mono font-bold text-gray-800"
                    @input="onBranchConsumeInput(m, $event)"
                  />
                  <span class="text-gray-600 font-normal text-xs mr-1">{{ m.consume_unit }}</span>
                </template>
                <template v-else>{{ m.branch_stock_consume }} {{ m.consume_unit }}</template>
              </td>
              <td class="p-3">{{ m.alert_pieces != null ? m.alert_pieces + ' ' + (m.unit || '') : '—' }}</td>
              <td v-if="branchDetail.can_edit_stock" class="p-3 text-center whitespace-nowrap">
                <template v-if="branchStockEdit.productId === m.id">
                  <button type="button" class="btn-primary text-xs py-1 px-2" @click="saveBranchStock(m)">حفظ</button>
                  <button type="button" class="btn-gray text-xs py-1 px-2 mr-1" @click="cancelBranchStockEdit">إلغاء</button>
                </template>
                <button v-else type="button" class="btn-yellow text-xs py-1 px-2" @click="startBranchStockEdit(m)">تعديل</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="no-print">
        <h3 class="text-lg font-bold text-gray-800 mb-2">سحوبات اليوم — {{ branchDetail.branch_name }}</h3>
        <p v-if="branchDetail.businessDayLabel" class="text-sm text-blue-700 bg-blue-50 rounded-lg px-3 py-2 mb-4 inline-block">
          {{ branchDetail.businessDayLabel }}
        </p>
        <div class="sm:hidden space-y-3">
          <p
            v-if="!branchDetail.todayPulls?.length"
            class="text-center text-gray-500 p-6 bg-white rounded-xl border border-gray-200"
          >
            لا توجد سحوبات لهذا اليوم.
          </p>
          <div
            v-for="pull in branchDetail.todayPulls"
            :key="'pull-card-' + pull.id"
            class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm"
          >
            <div class="font-semibold text-gray-800">{{ pull.product_name }}</div>
            <div class="mt-2 grid grid-cols-2 gap-2 text-sm">
              <div>
                <span class="text-gray-500">الوقت</span>
                <div class="font-medium">{{ pull.received_at }}</div>
              </div>
              <div>
                <span class="text-gray-500">القطع</span>
                <div class="font-medium">{{ pull.piece_count }} {{ pull.unit }}</div>
              </div>
            </div>
            <div class="mt-2 text-sm">
              <span class="text-gray-500">الكود</span>
              <div class="font-mono text-xs mt-0.5 break-all">{{ pull.label_code }}</div>
            </div>
          </div>
        </div>

        <div class="hidden sm:block overflow-x-auto border border-gray-200 rounded-xl bg-white">
          <table class="w-full text-sm border-collapse">
            <thead class="bg-gray-100">
              <tr>
                <th class="border border-gray-200 p-2">الوقت</th>
                <th class="border border-gray-200 p-2">المادة</th>
                <th class="border border-gray-200 p-2">القطع</th>
                <th class="border border-gray-200 p-2">الكود</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!branchDetail.todayPulls?.length">
                <td colspan="4" class="text-center p-6 text-gray-500">لا توجد سحوبات لهذا اليوم.</td>
              </tr>
              <tr v-for="pull in branchDetail.todayPulls" :key="pull.id">
                <td class="border border-gray-200 p-2 text-center">{{ pull.received_at }}</td>
                <td class="border border-gray-200 p-2 text-center">{{ pull.product_name }}</td>
                <td class="border border-gray-200 p-2 text-center">{{ pull.piece_count }} {{ pull.unit }}</td>
                <td class="border border-gray-200 p-2 text-center font-mono text-xs">{{ pull.label_code }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>

    </template>

    <div
      v-if="pageTab === 'materials' && isCentralView && printModal.open"
      class="sticker-print-modal fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
      @click.self="closePrintModal"
    >
      <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-6" dir="rtl">
        <h3 class="no-print text-lg font-bold text-gray-800 mb-2">طباعة كود — {{ printModal.materialName }}</h3>

        <p v-if="!printModal.created" class="no-print text-sm text-gray-600 mb-4">
          أدخل عدد القطع المراد تكويدها (تُسحب لاحقاً من الفرع عبر الباركود).
        </p>

        <p v-else class="no-print text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg py-2 px-3 mb-4">
          تم تكويد {{ printModal.piece_count }} {{ printModal.unit || 'قطعة' }} — بانتظار سحب الفرع.
        </p>

        <div v-if="!printModal.created" class="no-print">
          <label class="block text-gray-700 text-sm mb-1">عدد القطع</label>
          <input
            v-model.number="printModal.piece_count"
            type="number"
            step="any"
            min="0.001"
            class="w-full border rounded-lg p-3 mb-4"
          />
        </div>

        <div v-else>
          <div class="no-print border border-gray-200 rounded-lg p-3 mb-3 bg-gray-50">
            <p class="text-sm text-gray-700 mb-1">
              الكود: <span class="font-mono break-all">{{ printModal.label_code }}</span>
            </p>
            <div class="flex justify-center mb-2">
              <svg ref="barcodeSvgPreview" class="max-w-full h-auto"></svg>
            </div>
          </div>

          <p class="no-print text-xs text-gray-600 mb-4">
            يُسحب الكود من الفرع لإضافة الكمية لمخزون ذلك الفرع.
          </p>
        </div>

        <div class="no-print flex gap-2 justify-end">
          <button type="button" class="px-4 py-2 rounded-lg bg-gray-200 hover:bg-gray-300" @click="closePrintModal">
            إغلاق
          </button>
          <button
            v-if="!printModal.created"
            type="button"
            class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700"
            @click="submitPrintLabel"
          >
            متابعة للطباعة
          </button>
          <button
            v-else
            type="button"
            class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700"
            @click="doPrint"
          >
            طباعة
          </button>
        </div>
      </div>
    </div>

    <!-- ورقة طباعة منفصلة (خارج المودال) لتجنب مشاكل المعاينة -->
    <div
      v-if="pageTab === 'materials' && printModal.open && printModal.created && printModal.label_code"
      class="sticker-print-sheet print-only sticker-label"
      dir="rtl"
    >
      <p class="sticker-title">{{ printModal.materialName }}</p>
      <p class="sticker-qty">{{ printModal.piece_count }} {{ printModal.unit || 'قطعة' }}</p>
      <div class="sticker-barcode-wrap">
        <svg ref="barcodeSvgPrint" class="sticker-barcode"></svg>
      </div>
      <p class="sticker-code">{{ printModal.label_code }}</p>
    </div>
  </div>
</template>

<script>
import { Inertia } from "@inertiajs/inertia";
import { renderPreviewBarcode, renderPrintBarcode } from '@/utils/barcodeLabel';
import AppLayout from '@/Layouts/AppLayout.vue';
import FridgePanel from '@/Components/Admin/FridgePanel.vue';

export default {
  layout: AppLayout,
  components: { FridgePanel },
  props: {
    rawMaterials: Array,
    rawMaterialCategories: {
      type: Array,
      default: () => [],
    },
    filters: {
      type: Object,
      default: () => ({ category_id: '' }),
    },
    hubBranches: {
      type: Array,
      default: () => [],
    },
    branchDetail: {
      type: Object,
      default: null,
    },
    fridge: {
      type: Object,
      default: () => ({ configs: [], finishedProducts: [], stocks: [] }),
    },
  },
  computed: {
    canManageFridge() {
      return this.isAdmin || this.isSuperAdmin;
    },
    isCentralView() {
      return this.viewScopeSelect === 'central';
    },
    userRoles() {
      return this.$page?.props?.auth?.user?.roles || [];
    },
    isSuperAdmin() {
      return this.userRoles.includes('super admin');
    },
    isAdmin() {
      return this.userRoles.includes('admin');
    },
    isCashier() {
      return this.userRoles.includes('cashier');
    },
    canReceive() {
      return this.isCashier || this.isAdmin || this.isSuperAdmin;
    },
    canAddRaw() {
      return this.isSuperAdmin;
    },
    canPrint() {
      return this.isAdmin || this.isSuperAdmin;
    },
    canEdit() {
      return this.isAdmin || this.isSuperAdmin;
    },
    canDelete() {
      return this.isSuperAdmin;
    },
    canManageRawCategories() {
      return this.isSuperAdmin;
    },
    showTableTools() {
      return !this.isCashier && (this.isAdmin || this.isSuperAdmin);
    },
  },
  data() {
    return {
      pageTab: this.filters?.tab === 'fridge' ? 'fridge' : 'materials',
      viewScopeSelect: this.filters?.view_scope != null ? String(this.filters.view_scope) : 'central',
      filterCategoryId: this.filters?.category_id != null && this.filters.category_id !== '' ? String(this.filters.category_id) : '',
      rawMaterialsLocal: this.rawMaterials,
      printModal: {
        open: false,
        materialId: null,
        materialName: '',
        piece_count: 1,
        created: false,
        label_code: '',
      },
      branchStockEdit: {
        productId: null,
        pieces: 0,
        consume: 0,
      },
      branchStockSaving: false,
    };
  },
  watch: {
    rawMaterials: {
      deep: true,
      handler(val) {
        this.rawMaterialsLocal = val;
      },
    },
    filters: {
      deep: true,
      handler(val) {
        const id = val?.category_id;
        this.filterCategoryId = id != null && id !== '' ? String(id) : '';
        if (val?.view_scope != null) {
          this.viewScopeSelect = String(val.view_scope);
        }
        if (val?.tab === 'fridge' || val?.tab === 'materials') {
          this.pageTab = val.tab;
        }
      },
    },
  },
  methods: {
    filterQueryParams() {
      const params = { view_scope: this.viewScopeSelect, tab: this.pageTab };
      if (this.filterCategoryId) {
        params.category_id = this.filterCategoryId;
      }
      return params;
    },
    setPageTab(tab) {
      this.pageTab = tab;
      Inertia.get(route('admin.raw-materials.index'), this.filterQueryParams(), {
        preserveState: true,
        replace: true,
      });
    },
    applyViewScope() {
      if (this.viewScopeSelect !== 'central') {
        this.filterCategoryId = this.filterCategoryId || '';
      }
      const params = this.filterQueryParams();
      if (this.pageTab === 'fridge') {
        params.tab = 'fridge';
      }
      Inertia.get(route('admin.raw-materials.index'), params, {
        preserveState: true,
        replace: true,
      });
    },
    applyFilters() {
      Inertia.get(route('admin.raw-materials.index'), this.filterQueryParams(), {
        preserveState: true,
        replace: true,
      });
    },
    openPrintModal(material) {
      this.printModal = {
        open: true,
        materialId: material.id,
        materialName: material.name,
        piece_count: 1,
        created: false,
        label_code: '',
      };
    },
    closePrintModal() {
      this.printModal.open = false;
    },
    doPrint() {
      window.print();
    },
    submitPrintLabel() {
      const n = parseFloat(this.printModal.piece_count);
      if (!this.printModal.materialId || !n || n < 0.001) {
        alert('أدخل عدد قطع صالحاً.');
        return;
      }

      // AJAX call to avoid redirect to another page (easy UX for testing).
      window.axios
        .post(route('admin.raw-materials.labels.store', this.printModal.materialId), {
          piece_count: n,
        })
        .then((res) => {
          const d = res.data || {};
          this.printModal.created = true;
          this.printModal.label_code = d.label_code || '';
          this.printModal.consume_amount = d.consume_amount || null;

          // Update pending pieces immediately on the list.
          const m = this.rawMaterialsLocal.find((x) => x.id === this.printModal.materialId);
          if (m) {
            m.pending_pieces = (parseFloat(m.pending_pieces) || 0) + n;
          }

          this.$nextTick(() => {
            if (!this.printModal.label_code) return;
            const previewEl = this.$refs.barcodeSvgPreview;
            const printEl = this.$refs.barcodeSvgPrint;

            if (previewEl) {
              renderPreviewBarcode(previewEl, this.printModal.label_code);
            }
            if (printEl) {
              renderPrintBarcode(printEl, this.printModal.label_code);
            }
          });
        })
        .catch((err) => {
          const msg = err?.response?.data?.message || 'حدث خطأ أثناء تكويد الباركود.';
          alert(msg);
        });
    },
    deleteMaterial(id) {
      if (confirm("هل أنت متأكد من حذف هذه المادة الخام؟")) {
        Inertia.delete(route("admin.raw-materials.destroy", id));
      }
    },
    formatBranchStockNum(n) {
      if (Number.isNaN(n)) return '';
      return n % 1 === 0 ? n : parseFloat(n.toFixed(4));
    },
    branchQpu(m) {
      const qpu = parseFloat(m.quantity_per_unit);
      return qpu > 0 ? qpu : 1;
    },
    startBranchStockEdit(m) {
      const pieces = parseFloat(m.branch_stock_pieces) || 0;
      const consume = parseFloat(m.branch_stock_consume) || 0;
      this.branchStockEdit = {
        productId: m.id,
        pieces: this.formatBranchStockNum(pieces),
        consume: this.formatBranchStockNum(consume),
      };
    },
    cancelBranchStockEdit() {
      this.branchStockEdit = { productId: null, pieces: 0, consume: 0 };
    },
    onBranchPiecesInput(m, event) {
      const raw = event.target.value;
      const pieces = raw === '' ? NaN : parseFloat(raw);
      this.branchStockEdit.pieces = raw === '' ? '' : pieces;
      if (Number.isNaN(pieces)) {
        this.branchStockEdit.consume = '';
        return;
      }
      this.branchStockEdit.consume = this.formatBranchStockNum(pieces * this.branchQpu(m));
    },
    onBranchConsumeInput(m, event) {
      const raw = event.target.value;
      const consume = raw === '' ? NaN : parseFloat(raw);
      this.branchStockEdit.consume = raw === '' ? '' : consume;
      if (Number.isNaN(consume)) {
        this.branchStockEdit.pieces = '';
        return;
      }
      const qpu = this.branchQpu(m);
      this.branchStockEdit.pieces = this.formatBranchStockNum(consume / qpu);
    },
    saveBranchStock(m) {
      if (!this.branchDetail?.branch_id) return;
      const consume = parseFloat(this.branchStockEdit.consume);
      if (Number.isNaN(consume) || consume < 0) {
        alert('أدخل كمية استهلاكية صالحة (0 أو أكثر).');
        return;
      }
      this.branchStockSaving = true;
      Inertia.put(
        route('admin.raw-materials.branch-stock.update', {
          branch: this.branchDetail.branch_id,
          raw_material: m.id,
        }),
        {
          stock_consume: consume,
          category_id: this.filterCategoryId || undefined,
        },
        {
          preserveScroll: true,
          onFinish: () => {
            this.branchStockSaving = false;
            this.cancelBranchStockEdit();
          },
        }
      );
    },
    isStockLow(material) {
        if (!material.stock_alert_threshold) return false;
        return parseFloat(material.stock) <= parseFloat(material.stock_alert_threshold);
    },
    formatStockUnits(material) {
      if (!material.quantity_per_unit) return material.stock;
      const u = parseFloat(material.stock) / parseFloat(material.quantity_per_unit);
      return u % 1 === 0 ? u : parseFloat(u).toFixed(2);
    },
    formatStockConsume(material) {
      const s = parseFloat(material.stock);
      return s % 1 === 0 ? s : s.toFixed(2);
    },
    formatPendingPieces(material) {
      const p = parseFloat(material.pending_pieces);
      if (Number.isNaN(p)) return '0';
      return p % 1 === 0 ? p : p.toFixed(2);
    },
    formatQuantityPerUnit(material) {
      const q = parseFloat(material.quantity_per_unit);
      if (!q && q !== 0) return '—';
      return q % 1 === 0 ? q : q.toFixed(2);
    },
    formatAlertThreshold(material) {
      if (material.stock_alert_threshold == null || material.stock_alert_threshold === '') return 'لم يحدد';
      const t = parseFloat(material.stock_alert_threshold);
      if (material.quantity_per_unit) {
        const qpu = parseFloat(material.quantity_per_unit);
        const units = t / qpu;
        const u = units % 1 === 0 ? units : parseFloat(units).toFixed(2);
        return u + ' ' + (material.unit || 'قطعة');
      }
      return t + ' ' + (material.consume_unit || '');
    },
  },
};
</script>

<style scoped>
.btn-primary {
  @apply bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition shadow-md;
}
.btn-gray {
  @apply bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg transition shadow-md;
}
.btn-blue {
  @apply bg-sky-600 hover:bg-sky-700 text-white font-bold py-2 px-4 rounded-lg transition shadow-md;
}
.btn-blue-outline {
  @apply border-2 border-sky-600 text-sky-700 hover:bg-sky-50 font-bold py-2 px-4 rounded-lg transition;
}
.btn-yellow {
  @apply bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2 px-4 rounded-lg transition;
}
.btn-green {
  @apply bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg transition;
}
.btn-red {
  @apply bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-lg transition;
}

/* Styles for responsive table */
@media (max-width: 640px) {
  td[data-label]::before {
    content: attr(data-label) " :";
    font-weight: bold;
    display: inline-block;
    margin-right: 0.5rem; /* Equivalent to mr-2 in Tailwind */
    min-width: 140px; /* Adjust as needed */
    text-align: right;
  }

  td.p-4 {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 0.75rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid #e5e7eb; /* gray-200 */
  }
  
  td > * {
    flex-grow: 1;
    text-align: left;
  }
  
  td > .flex {
      justify-content: flex-end;
  }

  tr.block:last-child td:last-child {
    border-bottom: none;
  }
}
</style>