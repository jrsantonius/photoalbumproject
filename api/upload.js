import { put, list } from '@vercel/blob';
import { limits, response, safeSession } from './_config.js';

const allowed = new Set(['image/jpeg', 'image/png', 'image/webp']);

export async function POST(request) {
  try {
    const form = await request.formData();
    const files = [...form.getAll('photos[]'), ...form.getAll('photos')].filter((item) => item instanceof File);
    if (!files.length) return response({ success:false, message:'Tidak ada file yang dikirim.' }, 400);

    const header = safeSession(request.headers.get('x-session-id') || '');
    const sessionId = header || crypto.randomUUID();
    const existing = await list({ prefix:`uploads/${sessionId}/`, limit:limits.maxPhotos + 1 });
    if (existing.blobs.length + files.length > limits.maxPhotos) {
      return response({ success:false, message:`Maksimal ${limits.maxPhotos} foto per album.` }, 400);
    }

    const saved = [];
    for (const file of files) {
      if (!allowed.has(file.type)) return response({ success:false, message:`${file.name}: format harus JPG, PNG, atau WEBP.` }, 400);
      if (file.size > limits.maxFileSizeMB * 1024 * 1024) return response({ success:false, message:`${file.name}: ukuran melebihi ${limits.maxFileSizeMB}MB.` }, 400);
      const ext = file.type === 'image/png' ? 'png' : file.type === 'image/webp' ? 'webp' : 'jpg';
      const filename = `${Date.now()}-${crypto.randomUUID().slice(0,8)}.${ext}`;
      const blob = await put(`uploads/${sessionId}/${filename}`, file, {
        access:'public', addRandomSuffix:false, contentType:file.type
      });
      saved.push({ filename, originalname:file.name, url:blob.url, size:file.size });
    }
    return response({ success:true, sessionId, files:saved, message:`${saved.length} foto berhasil diunggah.` });
  } catch (error) {
    const missing = String(error?.message || '').includes('BLOB_READ_WRITE_TOKEN');
    return response({ success:false, message:missing ? 'Vercel Blob belum dikonfigurasi.' : 'Upload gagal diproses.' }, 500);
  }
}
