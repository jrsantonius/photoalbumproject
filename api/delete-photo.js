import { del } from '@vercel/blob';
import { response, safeSession } from './_config.js';

export async function POST(request) {
  try {
    const body = await request.json();
    const sessionId = safeSession(body.sessionId || '');
    const filename = String(body.filename || '').replace(/[^a-zA-Z0-9._-]/g, '');
    if (!sessionId || !filename) return response({ success:false, message:'Data foto tidak valid.' }, 400);
    await del(`uploads/${sessionId}/${filename}`);
    return response({ success:true, message:'Foto dihapus.' });
  } catch {
    return response({ success:false, message:'Gagal menghapus foto.' }, 500);
  }
}
