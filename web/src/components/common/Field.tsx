import * as React from "react";

/** Labeled form control with its validation message underneath. */
export function Field({
  label,
  error,
  children,
}: {
  label: string;
  error?: string | null;
  children: React.ReactNode;
}) {
  return (
    <label className="space-y-1.5 text-sm">
      <span className="font-medium text-foreground">{label}</span>
      {children}
      {error && <span className="block text-xs text-danger-foreground">{error}</span>}
    </label>
  );
}

/** Form-level error banner; renders nothing when there is no message. */
export function FormErrorBanner({ message }: { message: string | null }) {
  if (!message) return null;
  return (
    <div className="rounded-md bg-danger-subtle px-3 py-2 text-sm text-danger-foreground">
      {message}
    </div>
  );
}
