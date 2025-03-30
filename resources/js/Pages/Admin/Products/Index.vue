<template>
  <div class="container mx-auto p-6" dir="rtl">
    <h1 class="text-3xl font-extrabold mb-6 text-end text-gray-800">📦 إدارة المنتجات</h1>

    <!-- نموذج إضافة / تعديل المنتج -->
    <form @submit.prevent="submitProduct" class="bg-white shadow-lg rounded-lg p-6 mb-8 space-y-4">
      <div>
        <label class="block text-gray-700 font-medium">اسم المنتج</label>
        <input v-model="form.name" type="text" class="w-full p-3 border border-gray-300 rounded-lg focus:ring focus:ring-blue-300" required />
      </div>
      <div>
        <label class="block text-gray-700 font-medium">السعر</label>
        <input v-model="form.price" type="number" class="w-full p-3 border border-gray-300 rounded-lg focus:ring focus:ring-blue-300" required />
      </div>
      <div>
        <label class="block text-gray-700 font-medium">الكمية</label>
        <input v-model="form.quantity" type="number" class="w-full p-3 border border-gray-300 rounded-lg focus:ring focus:ring-blue-300" />
      </div>
      <div>
        <label class="block text-gray-700 font-medium">صورة المنتج</label>
        <input type="file" @change="handleFileUpload" class="w-full p-3 border border-gray-300 rounded-lg" />
      </div>
      <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-lg transition">
        {{ isEditing ? "🔄 تحديث المنتج" : "➕ إضافة المنتج" }}
      </button>
    </form>

    <!-- عرض قائمة المنتجات -->
    <div class="overflow-x-auto">
      <table class="w-full bg-white rounded-lg shadow-lg">
            <thead class="bg-gray-100">
            <tr class="text-gray-700 text-end">
                <th class="p-4">الاسم</th>
                <th class="p-4">السعر</th>
                <th class="p-4">الكمية</th>
                <th class="p-4 text-center">الصورة</th>
                <th class="p-4 text-center">الإجراءات</th> <!-- جعل العنوان في المنتصف -->
            </tr>
            </thead>
            <tbody>
            <tr v-for="product in products" :key="product.id" class="border-t text-end">
                <td class="p-4 font-semibold">{{ product.name }}</td>
                <td class="p-4 text-green-600 font-bold">${{ product.price }}</td>
                <td class="p-4">{{ product.quantity }}</td>
                <td class="p-4 text-center">
                <img v-if="product.image" :src="`/storage/${product.image}`" alt="صورة المنتج" class="h-16 w-16 object-cover rounded-lg shadow mx-auto">
                </td>
                <td class="p-4 text-center">
                <div class="flex space-x-2 justify-center"> <!-- ترتيب الأزرار بشكل أفقي -->
                    <button @click="editProduct(product)" class="bg-yellow-500 hover:bg-yellow-600 text-white mx-2 px-4 py-2 rounded-lg transition">
                    ✏️ تعديل
                    </button>
                    <button @click="deleteProduct(product.id)" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg transition">
                    🗑️ حذف
                    </button>
                </div>
                </td>
            </tr>
            </tbody>


      </table>
    </div>
  </div>
</template>

<script>
import { Inertia } from "@inertiajs/inertia";

export default {
  props: {
    products: Array,
  },
  data() {
    return {
      form: {
        id: null,
        name: "",
        price: "",
        quantity: "",
        image: null,
      },
      isEditing: false, // لمعرفة ما إذا كان المستخدم في وضع التعديل
    };
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
      if (this.form.image) {
        formData.append("image", this.form.image);
      }

      if (this.isEditing) {
        // تحديث المنتج
        Inertia.post(route("admin.products.update", this.form.id), formData, {
          onSuccess: () => this.resetForm(),
        });
      } else {
        // إضافة منتج جديد
        Inertia.post(route("admin.products.store"), formData, {
          onSuccess: () => this.resetForm(),
        });
      }
    },

    editProduct(product) {
      this.form.id = product.id;
      this.form.name = product.name;
      this.form.price = product.price;
      this.form.quantity = product.quantity;
      this.form.image = null; // لا نحمل الصورة مرة أخرى، يجب على المستخدم اختيارها عند التحديث
      this.isEditing = true;
    },

    deleteProduct(id) {
      if (confirm("هل أنت متأكد من حذف هذا المنتج؟")) {
        Inertia.delete(route("admin.products.destroy", id));
      }
    },

    resetForm() {
      this.form = { id: null, name: "", price: "", quantity: "", image: null };
      this.isEditing = false;
    },
  },
};
</script>
