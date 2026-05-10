export function base64UrlToArrayBuffer(value: string): ArrayBuffer {
  const normalized = String(value || '').trim();
  if (normalized === '') return new ArrayBuffer(0);
  const base64 = normalized.replace(/-/g, '+').replace(/_/g, '/');
  const padded = base64 + '='.repeat((4 - (base64.length % 4)) % 4);
  const binary = typeof atob === 'function'
    ? atob(padded)
    : Buffer.from(padded, 'base64').toString('binary');
  const out = new Uint8Array(binary.length);
  for (let index = 0; index < binary.length; index += 1) {
    out[index] = binary.charCodeAt(index);
  }
  return out.buffer;
}

export function arrayBufferToBase64Url(buffer: ArrayBuffer): string {
  const view = new Uint8Array(buffer || new ArrayBuffer(0));
  let binary = '';
  for (let index = 0; index < view.byteLength; index += 1) {
    binary += String.fromCharCode(view[index]);
  }
  const base64 = typeof btoa === 'function'
    ? btoa(binary)
    : Buffer.from(view).toString('base64');
  return base64.replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
}
