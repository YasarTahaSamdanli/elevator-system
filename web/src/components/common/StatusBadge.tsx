import { Badge } from "@/components/ui/badge";
import { cn } from "@/lib/utils";
import type { StatusMeta } from "@/lib/status";

interface StatusBadgeProps {
  meta: StatusMeta;
  /** show a leading colored dot (good for operational statuses) */
  dot?: boolean;
  className?: string;
}

/** Renders a domain status consistently from a StatusMeta (see lib/status.ts). */
export function StatusBadge({ meta, dot = true, className }: StatusBadgeProps) {
  return (
    <Badge variant={meta.variant} className={className}>
      {dot && <span className={cn("size-1.5 rounded-full", meta.dot)} />}
      {meta.label}
    </Badge>
  );
}
