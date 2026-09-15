import { limits, sizes, covers, papers, couriers, response, safeSession, calculate, redis } from './_config.js';

export async function POST(request) {
  try {
    const body = await request.json();
    const customer = body.customer || {};
    const shipping = body.shipping || {};
    const printOptions = body.printOptions || {};
    const photos = Array.isArray(body.photos) ? body.photos : [];
    const errors = [];
    if (!safeSession(body.sessionId || '')) errors.push('Session foto tidak valid.');
    if (!customer.name || !customer.email || !customer.phone) errors.push('Data pemesan belum lengkap.');
    if (!/^\S+@\S+\.\S+$/.test(customer.email || '')) errors.push('Email tidak valid.');
    if (!shipping.recipient || !shipping.address || !shipping.city || !shipping.province || !shipping.postalCode) errors.push('Alamat pengiriman belum lengkap.');
    if (!photos.length || photos.length > limits.maxPhotos) errors.push('Jumlah foto tidak valid.');
    if (!body.albumType || !body.template?.id) errors.push('Jenis album atau template belum dipilih.');
    if (!sizes.some((x) => x.code === printOptions.size)) errors.push('Ukuran album tidak valid.');
    if (!covers.some((x) => x.code === printOptions.cover)) errors.push('Cover tidak valid.');
    if (!papers.some((x) => x.code === printOptions.paper)) errors.push('Kertas tidak valid.');
    if (!couriers.some((x) => x.code === shipping.courier)) errors.push('Kurir tidak valid.');
    if (+printOptions.copies < 1 || +printOptions.copies > limits.maxCopies) errors.push('Jumlah buku tidak valid.');
    if (errors.length) return response({ success:false, message:errors.join(' '), errors }, 400);

    printOptions.copies = +printOptions.copies;
    const pricing = calculate(printOptions, shipping);
    const orderId = `TIS-${Date.now()}-${crypto.randomUUID().slice(0,6).toUpperCase()}`;
    const order = { ...body, orderId, pricing, status:'new', payment:{ provider:null, status:'not_required' }, createdAt:new Date().toISOString() };
    await redis(['SET', `order:${orderId}`, JSON.stringify(order)]);
    return response({ success:true, orderId, pricing, paymentLink:null, message:'Pesanan berhasil dicatat. Tim kami akan menghubungi Anda melalui WhatsApp.' });
  } catch (error) {
    const missing = error?.message === 'ORDER_STORAGE_NOT_CONFIGURED';
    return response({ success:false, message:missing ? 'Penyimpanan pesanan belum dikonfigurasi di Vercel.' : 'Pesanan gagal disimpan.' }, 500);
  }
}
