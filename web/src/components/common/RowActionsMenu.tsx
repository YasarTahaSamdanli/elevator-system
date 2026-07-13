import * as React from "react";
import { MoreHorizontal, Pencil, Trash2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";

/**
 * Standard per-row "..." menu: Düzenle + Sil, with an optional slot for
 * extra items in between. Omit `onEdit`/`onDelete` to hide those items.
 */
export function RowActionsMenu({
  ariaLabel,
  icon,
  onEdit,
  onDelete,
  children,
}: {
  ariaLabel: string;
  /** Custom trigger icon (e.g. a spinner while a row action runs). */
  icon?: React.ReactNode;
  onEdit?: () => void;
  onDelete?: () => void;
  children?: React.ReactNode;
}) {
  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="ghost" size="icon-sm" aria-label={ariaLabel}>
          {icon ?? <MoreHorizontal />}
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end">
        {onEdit && (
          <DropdownMenuItem onSelect={onEdit}>
            <Pencil className="size-4" />
            Düzenle
          </DropdownMenuItem>
        )}
        {children}
        {onDelete && (
          <DropdownMenuItem className="text-danger focus:text-danger" onSelect={onDelete}>
            <Trash2 className="size-4" />
            Sil
          </DropdownMenuItem>
        )}
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
