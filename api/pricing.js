import { limits, sizes, covers, papers, couriers, response } from './_config.js';

export function GET() {
  return response({ success:true, currency:'IDR', limits, sizes, covers, papers, couriers });
}
