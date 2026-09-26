export const warten = (ms: number) => new Promise<void>((r) => setTimeout(r, ms));

/** Host ohne www., klein. */
export function domainVon(url: string): string | null {
  try {
    const u = new URL(/^[a-z]+:\/\//i.test(url) ? url : 'http://' + url);
    return u.hostname.toLowerCase().replace(/^www\d?\./, '');
  } catch {
    return null;
  }
}

export function gleicheSite(a: string, b: string): boolean {
  const da = domainVon(a);
  const db = domainVon(b);
  return da !== null && da === db;
}

/** Normalisiert Text fuer die Signalsuche: klein, ohne Akzente, ein Leerzeichen. */
export function norm(s: string): string {
  return s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/\s+/g, ' ').trim();
}

export function kuerzen(s: string, max = 300): string {
  return s.length > max ? s.slice(0, max - 1) + '…' : s;
}

/** Pro Domain eine Mindestpause -- hoeflich, auch bei mehreren Pruefschritten. */
const letzterZugriff = new Map<string, number>();
export async function hoeflich(url: string, pauseMs: number): Promise<void> {
  const d = domainVon(url) ?? url;
  const zuletzt = letzterZugriff.get(d) ?? 0;
  const noch = zuletzt + pauseMs - Date.now();
  if (noch > 0) await warten(noch);
  letzterZugriff.set(d, Date.now());
}
