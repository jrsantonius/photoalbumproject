export const limits = { minPhotos: 10, maxPhotos: 40, maxFileSizeMB: 4, maxCopies: 10 };

export const sizes = [
  { code:'A5', label:'A5', description:'14.8 x 21 cm - ringkas & mudah dibawa', prices:{ softcover:150000, hardcover:200000 } },
  { code:'A4', label:'A4', description:'21 x 29.7 cm - ukuran paling populer', prices:{ softcover:200000, hardcover:280000 } },
  { code:'A3', label:'A3', description:'29.7 x 42 cm - tampilan besar & megah', prices:{ softcover:300000, hardcover:400000 } }
];
export const covers = [
  { code:'softcover', label:'Softcover', description:'Sampul lentur, laminasi doff' },
  { code:'hardcover', label:'Hardcover', description:'Sampul tebal, jahit benang' }
];
export const papers = [
  { code:'matte', label:'Matte', description:'Tidak memantul, warna kalem', surcharge:0 },
  { code:'glossy', label:'Glossy', description:'Mengkilap, warna tajam', surcharge:20000 },
  { code:'satin', label:'Satin', description:'Semi-kilap, kesan premium', surcharge:15000 }
];
export const couriers = [
  { code:'jnt', name:'J&T Express', cost:12000, eta:'2-3 hari' },
  { code:'jne_reg', name:'JNE Regular', cost:15000, eta:'3-5 hari' },
  { code:'sicepat', name:'SiCepat', cost:13000, eta:'2-3 hari' },
  { code:'jne_yes', name:'JNE YES', cost:25000, eta:'Esok hari tiba' },
  { code:'gosend', name:'GoSend Instant', cost:20000, eta:'Hari ini (Jabodetabek)' }
];

export function response(data, status = 200) {
  return Response.json(data, { status, headers:{ 'Cache-Control':'no-store' } });
}

export function safeSession(value = '') {
  return /^[a-zA-Z0-9-]{8,80}$/.test(value) ? value : '';
}

export function calculate(printOptions, shipping) {
  const size = sizes.find((item) => item.code === printOptions.size);
  const paper = papers.find((item) => item.code === printOptions.paper);
  const courier = couriers.find((item) => item.code === shipping.courier);
  const pricePerCopy = (size?.prices?.[printOptions.cover] || 0) + (paper?.surcharge || 0);
  const subtotal = pricePerCopy * printOptions.copies;
  const shippingCost = courier?.cost || 0;
  return { pricePerCopy, subtotal, shippingCost, total:subtotal + shippingCost };
}

export function redisConfig() {
  return {
    url: process.env.KV_REST_API_URL || process.env.UPSTASH_REDIS_REST_URL || '',
    token: process.env.KV_REST_API_TOKEN || process.env.UPSTASH_REDIS_REST_TOKEN || ''
  };
}

export async function redis(command) {
  const cfg = redisConfig();
  if (!cfg.url || !cfg.token) throw new Error('ORDER_STORAGE_NOT_CONFIGURED');
  const result = await fetch(cfg.url, {
    method:'POST',
    headers:{ Authorization:`Bearer ${cfg.token}`, 'Content-Type':'application/json' },
    body:JSON.stringify(command)
  });
  if (!result.ok) throw new Error('ORDER_STORAGE_FAILED');
  const data = await result.json();
  return data.result;
}
