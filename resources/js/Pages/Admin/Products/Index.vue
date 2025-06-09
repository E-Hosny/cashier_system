<template>
  <div class="container mx-auto p-6" dir="rtl">
    <h1 class="text-3xl font-bold mb-8 text-end text-gray-800">📦 إدارة المنتجات</h1>

    <!-- ✅ نموذج إضافة / تعديل منتج -->
    <div class="bg-white shadow-md rounded-xl p-6 mb-10 border">
      <h2 class="text-xl font-semibold text-gray-700 mb-4">{{ isEditing ? "🔄 تعديل المنتج" : "➕ منتج جديد" }}</h2>
      <form @submit.prevent="submitProduct" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-gray-700 mb-1">اسم المنتج</label>
            <input v-model="form.name" type="text" class="input-style" required />
          </div>

          <div>
            <label class="block text-gray-700 mb-1">السعر</label>
            <input v-model="form.price" type="number" class="input-style" required />
          </div>

          <div>
            <label class="block text-gray-700 mb-1">الكمية</label>
            <input v-model="form.quantity" type="number" class="input-style" />
          </div>

          <div>
            <label class="block text-gray-700 mb-1">الفئة</label>
            <select v-model="form.category_id" class="input-style">
              <option disabled value="">اختر فئة</option>
              <option v-for="cat in categoriesList" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
            </select>
          </div>

          <div class="md:col-span-2">
            <label class="block text-gray-700 mb-1">صورة المنتج</label>
            <input type="file" @change="handleFileUpload" class="input-style" />
          </div>
        </div>

        <button type="submit" class="btn-primary w-full">
          {{ isEditing ? "🔄 تحديث المنتج" : "➕ إضافة المنتج" }}
        </button>
      </form>
    </div>

    <!-- ✅ جدول عرض المنتجات -->
    <div class="overflow-x-auto mb-12">
      <table class="w-full bg-white border rounded-xl shadow text-end">
        <thead class="bg-gray-100">
          <tr>
            <th class="p-3">الاسم</th>
            <th class="p-3">السعر</th>
            <th class="p-3">الكمية</th>
            <th class="p-3 text-center">الصورة</th>
            <th class="p-3">الفئة</th>
            <th class="p-3 text-center">الإجراءات</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="product in products" :key="product.id" class="border-t">
            <td class="p-3">{{ product.name }}</td>
            <td class="p-3 text-green-600 font-bold">${{ product.price }}</td>
            <td class="p-3">{{ product.quantity }}</td>
            <td class="p-3 text-center">
              <img v-if="product.image" :src="`/storage/${product.image}`" class="h-14 w-14 object-cover rounded shadow inline-block">
            </td>
            <td class="p-3">{{ product.category?.name || "بدون فئة" }}</td>
            <td class="p-3 text-center">
              <div class="flex justify-center gap-2">
                <button @click="editProduct(product)" class="btn-yellow">✏️</button>
                <button @click="deleteProduct(product.id)" class="btn-red">🗑️</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- ✅ إدارة الفئات -->
    <div class="bg-white shadow-md rounded-xl p-6 border">
      <h2 class="text-xl font-semibold text-gray-700 mb-4">📁 إدارة الفئات</h2>
      <form @submit.prevent="addCategory" class="flex flex-col sm:flex-row gap-4 mb-4">
        <input v-model="newCategory" type="text" class="input-style flex-1" placeholder="اسم الفئة الجديدة" />
        <button class="btn-green">➕ حفظ</button>
      </form>
      <ul class="divide-y">
        <li v-for="cat in categoriesList" :key="cat.id" class="flex justify-between items-center py-3 px-2">
          <span>{{ cat.name }}</span>
          <div class="flex gap-2">
            <button @click="editCategory(cat)" class="text-yellow-600 font-bold">تعديل</button>
            <button @click="deleteCategory(cat.id)" class="text-red-600 font-bold">حذف</button>
          </div>
        </li>
      </ul>
    </div>
  </div>
</template>

<script>
import { Inertia } from "@inertiajs/inertia";

export default {
  props: {
    products: Array,
    categories: Array,
  },
  data() {
    return {
      categoriesList: [...this.categories],
      form: {
        id: null,
        name: "",
        price: "",
        quantity: "",
        image: null,
        category_id: "",
      },
      isEditing: false,
      newCategory: "",
      editingCategory: null,
    };
  },
  mounted() {
    this.fetchCategories();
  },
  methods: {
    handleFileUpload(event) {
      this.form.image = event.target.files[0];
    },

submitProduct() {
  const formData = new FormData();
  formData.append("name", this.form.name);
  formData.append("price", this.form.price);
  formData.append("quantity", this.form.quantity);
  formData.append("category_id", this.form.category_id);
  if (this.form.image) {
    formData.append("image", this.form.image);
  }

  if (this.isEditing) {
    Inertia.post(route("admin.products.update", this.form.id), formData, {
      onSuccess: () => this.resetForm(),
    });
  } else {
    Inertia.post(route("admin.products.store"), formData, {
      onSuccess: () => this.resetForm(),
    });
  }
},




    editProduct(product) {
      this.form = {
        id: product.id,
        name: product.name,
        price: product.price,
        quantity: product.quantity,
        image: null,
        category_id: product.category_id,
      };
      this.isEditing = true;
    },

    deleteProduct(id) {
      if (confirm("هل أنت متأكد من حذف هذا المنتج؟")) {
        Inertia.delete(route("admin.products.destroy", id));
      }
    },

    resetForm() {
      this.form = {
        id: null,
        name: "",
        price: "",
        quantity: "",
        image: null,
        category_id: "",
      };
      this.isEditing = false;
    },

    fetchCategories() {
      fetch("/categories")
        .then((res) => res.json())
        .then((data) => {
          this.categoriesList = data;
        });
    },

  addCategory() {
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content");

  if (!csrfToken) {
    alert("CSRF token not found!");
    return;
  }

  const url = this.editingCategory
    ? `/categories/${this.editingCategory}`
    : "/categories";

  const method = this.editingCategory ? "PUT" : "POST";

  fetch(url, {
    method,
    headers: {
      "Content-Type": "application/json",
      "X-CSRF-TOKEN": csrfToken,
    },
    body: JSON.stringify({ name: this.newCategory }),
  })
    .then(() => {
      this.newCategory = "";
      this.editingCategory = null;
      this.fetchCategories();
    })
    .catch((error) => {
      console.error("حدث خطأ أثناء حفظ الفئة:", error);
    });
},


    editCategory(cat) {
      this.newCategory = cat.name;
      this.editingCategory = cat.id;
    },

deleteCategory(id) {
  if (!confirm("هل أنت متأكد من حذف هذه الفئة؟")) {
    return;
  }

  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content");

  if (!csrfToken) {
    alert("CSRF token not found!");
    return;
  }

  fetch(`/categories/${id}`, {
    method: "DELETE",
    headers: {
      "X-CSRF-TOKEN": csrfToken,
    },
  })
    .then(() => {
      this.fetchCategories();
    })
    .catch((error) => {
      console.error("حدث خطأ أثناء حذف الفئة:", error);
    });
},

  },
};
</script>

<style scoped>
.input-style {
  @apply w-full p-3 border border-gray-300 rounded-lg focus:ring focus:ring-blue-300;
}
.btn-primary {
  @apply bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition;
}
.btn-green {
  @apply bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded transition;
}
.btn-yellow {
  @apply bg-yellow-500 hover:bg-yellow-600 text-white font-bold px-4 py-1 rounded;
}
.btn-red {
  @apply bg-red-500 hover:bg-red-600 text-white font-bold px-4 py-1 rounded;
}
</style>