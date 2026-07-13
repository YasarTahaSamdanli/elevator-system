/**
 * Form value helpers shared by every CRUD page. Forms keep all values as
 * strings (input state); these convert to/from the API payload shapes.
 */

/** "" (or whitespace) → null, otherwise the trimmed string. */
export const blankToNull = (value: string): string | null => {
  const trimmed = value.trim();
  return trimmed === "" ? null : trimmed;
};

/** "" (or whitespace) → null, otherwise Number(value). */
export const numericOrNull = (value: string): number | null => {
  const trimmed = value.trim();
  return trimmed === "" ? null : Number(trimmed);
};

/** First validation message for a field, or null. */
export function fieldError(errors: Record<string, string[]>, field: string): string | null {
  return errors[field]?.[0] ?? null;
}

/** Today as YYYY-MM-DD for date input defaults. */
export const todayIso = (): string => new Date().toISOString().slice(0, 10);

/** Normalize an API date(time) string to the YYYY-MM-DD shape date inputs need. */
export const toDateInput = (value: string): string => value.slice(0, 10);

const pad = (n: number) => String(n).padStart(2, "0");

/** ISO datetime (UTC) → local "YYYY-MM-DDTHH:mm" for datetime-local inputs. */
export function toLocalInput(value: string | null): string {
  if (!value) return "";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "";
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(
    date.getHours()
  )}:${pad(date.getMinutes())}`;
}

/** datetime-local value → ISO string (UTC), empty → null. */
export function fromLocalInput(value: string): string | null {
  const trimmed = value.trim();
  if (trimmed === "") return null;
  const date = new Date(trimmed);
  return Number.isNaN(date.getTime()) ? null : date.toISOString();
}

/** Build FilterSelect/Select options from a status-meta record (lib/status.ts). */
export function metaOptions<K extends string>(
  meta: Record<K, { label: string }>
): { value: K; label: string }[] {
  return (Object.keys(meta) as K[]).map((value) => ({ value, label: meta[value].label }));
}
