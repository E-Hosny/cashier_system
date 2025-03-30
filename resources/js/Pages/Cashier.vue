<template>
  <div class="container mx-auto p-6" dir="rtl">
    <h1 class="text-3xl font-extrabold mb-6 text-end text-gray-800">🍹 واجهة الكاشير</h1>

    <div class="flex justify-between gap-6">
      
      <!-- السلة -->
      <div class="w-full sm:w-1/3 lg:w-1/4 bg-gray-100 p-4 rounded-lg shadow-md">
        <h2 class="text-xl font-semibold text-end mb-4">🛒 السلة</h2>
        
        <div v-for="(item, index) in cart" :key="item.id" class="flex justify-between items-center mb-2">
          <div>
            <span class="font-medium">{{ item.name }}</span> 
            - <span class="text-green-600">${{ item.price }}</span> 
          </div>
          <div class="flex items-center">
            <span class="text-gray-500 mx-1">الكمية: {{ item.quantity }}</span>
            <button @click="updateQuantity(index, 1)" class="bg-yellow-500 hover:bg-yellow-600 text-white px-2 py-1 rounded-lg transition ml-2">+</button>
            <button @click="updateQuantity(index, -1)" :disabled="item.quantity <= 1" class="mx-1 bg-yellow-500 hover:bg-yellow-600 text-white px-2 py-1 rounded-lg transition mr-2">-</button>
          </div>
          <button @click="removeFromCart(index)" class="bg-red-500 hover:bg-red-600 text-white px-2 py-1 rounded-lg transition">حذف</button>
        </div>

        <div class="mt-4">
          <p class="font-bold text-xl text-end">الإجمالي: ${{ totalAmount }}</p>
        </div>

        <button @click="checkout" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 rounded-lg mt-4 transition">إصدار الفاتورة</button>
        <button @click="clearCart" class="w-full bg-red-500 hover:bg-red-600 text-white font-bold py-3 rounded-lg mt-2 transition">تصفير السلة 🗑️</button>
      </div>

      <!-- المنتجات -->
      <div class="w-full sm:w-2/3 lg:w-3/4">
        <div class="mb-4">
          <input v-model="searchQuery" type="text" placeholder="ابحث عن عصير..." class="w-full p-3 border border-gray-300 rounded-lg" @input="searchProduct" />
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-4 xl:grid-cols-4 gap-6 mb-6">
          <div v-for="product in filteredProducts" :key="product.id" class="bg-white rounded-lg shadow-lg overflow-hidden transform transition-all hover:scale-105 flex flex-col border border-gray-200">
            <div class="relative w-full h-40">
              <img v-if="product.image" :src="`/storage/${product.image}`" alt="صورة المنتج" class="w-full h-full object-contain rounded-t-lg" />
            </div>
            <div class="p-4 flex-1 flex flex-col justify-between">
              <h3 class="text-xl font-semibold text-gray-800 text-center">{{ product.name }}</h3>
              <p class="text-center text-green-600 text-lg font-bold">${{ product.price }}</p>
              <div class="mt-4 text-center">
                <input v-model.number="product.quantityToAdd" type="number" min="1" :placeholder="`العدد`" class="p-2 border border-gray-300 rounded-lg text-center" />
                <button @click="addToCart(product, product.quantityToAdd || 1)" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition focus:outline-none focus:ring-2 focus:ring-blue-500 mt-2">إضافة للسلة</button>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>

    <!-- نافذة عرض الفاتورة -->
    <div v-if="showInvoice" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center">
      <div class="bg-white w-3/4 h-5/6 p-6 rounded-lg shadow-lg flex flex-col">
        <h2 class="text-2xl font-bold text-center mb-4">📜 عرض الفاتورة</h2>
        <iframe :src="`/invoice/${orderId}`" class="w-full flex-1 border-none"></iframe>
        <div class="flex justify-between mt-4">
          <button @click="printInvoice" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition">🖨️ طباعة الفاتورة</button>
          <button @click="showInvoice = false" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg transition">إغلاق</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  props: {
    products: Array,
  },
  data() {
    return {
      searchQuery: '',
      cart: [],
      orderId: null,
      showInvoice: false,
      filteredProducts: this.products.map(product => ({ ...product, quantityToAdd: 1 })),
    };
  },
  computed: {
    totalAmount() {
      return this.cart.reduce((total, item) => total + item.price * item.quantity, 0).toFixed(2);
    }
  },
  methods: {
    searchProduct() {
      this.filteredProducts = this.products.filter(product => 
        product.name.toLowerCase().includes(this.searchQuery.toLowerCase())
      );
    },
    addToCart(product, quantity) {
      const found = this.cart.find(item => item.id === product.id);
      if (found) {
        found.quantity += quantity;
      } else {
        this.cart.push({ ...product, quantity });
      }
      product.quantityToAdd = 1;
    },
    removeFromCart(index) {
      this.cart.splice(index, 1);
    },
    updateQuantity(index, change) {
      const item = this.cart[index];
      item.quantity += change;
      if (item.quantity <= 0) {
        this.removeFromCart(index);
      }
    },
    checkout() {
      axios.post('/checkout', { items: this.cart, total: this.totalAmount })
        .then(response => {
          alert('تم تسجيل الطلب بنجاح! رقم الطلب: ' + response.data.order_id);
          this.orderId = response.data.order_id;
          this.showInvoice = true;
          this.cart = [];
        })
        .catch(error => {
          alert('حدث خطأ أثناء الدفع');
        });
    },
    printInvoice() {
      const invoiceWindow = window.open(`/invoice/${this.orderId}`, '_blank');
      invoiceWindow.print();
    },
    clearCart() {
      this.cart = [];
    }
  }
};
</script>
