import { response, redis } from './_config.js';

export async function GET(request) {
  const orderId = new URL(request.url).searchParams.get('orderId') || '';
  if (!/^TIS-[0-9]+-[A-Z0-9]+$/.test(orderId)) return response({ success:false, message:'Order ID tidak valid.' }, 400);
  try {
    const raw = await redis(['GET', `order:${orderId}`]);
    if (!raw) return response({ success:false, message:'Pesanan tidak ditemukan.' }, 404);
    const order = JSON.parse(raw);
    return response({ success:true, order:{ orderId, status:order.status, pricing:order.pricing, createdAt:order.createdAt } });
  } catch {
    return response({ success:false, message:'Status pesanan tidak tersedia.' }, 500);
  }
}
