import axios from '../bootstrap';

const apiBase = import.meta.env.VITE_API_URL || '';

// Sanctum SPA requires this cookie call once before any stateful POST/login
export async function ensureCsrfCookie() {
    await axios.get(`${apiBase}/sanctum/csrf-cookie`);
}

export default axios;
