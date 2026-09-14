export const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
export function dollarsToCents(value) {
  const match = /^(\d{1,8})(?:\.(\d{1,2}))?$/.exec(String(value).trim());
  if (!match) throw new Error('Enter a price with no more than two decimal places.');
  const cents = Number(match[1]) * 100 + Number((match[2] || '').padEnd(2, '0'));
  if (!Number.isSafeInteger(cents) || cents < 100 || cents > 9999999900) throw new Error('Enter an offer from $1 to $99,999,999.');
  return cents;
}
export function safeUrl(value, base) {
  try { const url = new URL(value, base); return ['https:', 'http:'].includes(url.protocol) ? url.href : ''; } catch { return ''; }
}
export function formatMoney(cents) {
  return Number.isSafeInteger(cents) ? new Intl.NumberFormat('en-US', {style:'currency',currency:'USD',maximumFractionDigits:cents % 100 ? 2 : 0}).format(cents / 100) : 'Price on request';
}
