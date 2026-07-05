/** Presentation formatters — Turkish locale, no business logic. */

const trDate = new Intl.DateTimeFormat("tr-TR", {
  day: "2-digit",
  month: "short",
  year: "numeric",
});

const trDateTime = new Intl.DateTimeFormat("tr-TR", {
  day: "2-digit",
  month: "short",
  year: "numeric",
  hour: "2-digit",
  minute: "2-digit",
});

const trCurrency = new Intl.NumberFormat("tr-TR", {
  style: "currency",
  currency: "TRY",
  maximumFractionDigits: 0,
});

const trNumber = new Intl.NumberFormat("tr-TR");

export function formatDate(value: string | null | undefined): string {
  if (!value) return "—";
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return "—";
  return trDate.format(d);
}

export function formatDateTime(value: string | null | undefined): string {
  if (!value) return "—";
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return "—";
  return trDateTime.format(d);
}

export function formatCurrency(value: number | null | undefined): string {
  if (value == null) return "—";
  return trCurrency.format(value);
}

export function formatNumber(value: number | null | undefined): string {
  if (value == null) return "—";
  return trNumber.format(value);
}

/** Relative time in Turkish, e.g. "3 saat önce" — used for activity/notifications. */
export function timeAgo(value: string, now: Date = new Date()): string {
  const then = new Date(value).getTime();
  const diffSec = Math.round((now.getTime() - then) / 1000);
  const abs = Math.abs(diffSec);
  const rtf = new Intl.RelativeTimeFormat("tr-TR", { numeric: "auto" });
  const units: [Intl.RelativeTimeFormatUnit, number][] = [
    ["year", 31536000],
    ["month", 2592000],
    ["week", 604800],
    ["day", 86400],
    ["hour", 3600],
    ["minute", 60],
    ["second", 1],
  ];
  for (const [unit, secs] of units) {
    if (abs >= secs || unit === "second") {
      return rtf.format(-Math.round(diffSec / secs), unit);
    }
  }
  return "";
}

/** Days remaining until a date (negative if past). */
export function daysUntil(value: string, now: Date = new Date()): number {
  const then = new Date(value).getTime();
  return Math.ceil((then - now.getTime()) / 86400000);
}

export function initials(name: string): string {
  return name
    .split(" ")
    .filter(Boolean)
    .slice(0, 2)
    .map((n) => n[0]?.toUpperCase() ?? "")
    .join("");
}
