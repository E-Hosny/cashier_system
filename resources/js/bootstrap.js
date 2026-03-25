import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Ensure axios requests include Laravel CSRF token.
const token = document.head.querySelector('meta[name="csrf-token"]')?.content;
if (token) {
  window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
}
