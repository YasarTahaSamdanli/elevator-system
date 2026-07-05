import * as React from "react";
import { cn } from "@/lib/utils";

interface ToolbarProps {
  /** left cluster: search + filters */
  children: React.ReactNode;
  /** right cluster: view toggles / secondary actions */
  actions?: React.ReactNode;
  className?: string;
}

export function Toolbar({ children, actions, className }: ToolbarProps) {
  return (
    <div
      className={cn(
        "flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between",
        className
      )}
    >
      <div className="flex flex-1 flex-col flex-wrap gap-2 sm:flex-row sm:items-center">
        {children}
      </div>
      {actions && <div className="flex items-center gap-2">{actions}</div>}
    </div>
  );
}
