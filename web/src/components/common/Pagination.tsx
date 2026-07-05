import { ChevronLeft, ChevronRight } from "lucide-react";
import { Button } from "@/components/ui/button";
import type { PaginationMeta } from "@/lib/api";

interface PaginationProps {
  pagination: PaginationMeta | null;
  onPageChange: (page: number) => void;
}

export function Pagination({ pagination, onPageChange }: PaginationProps) {
  if (!pagination || pagination.total_pages <= 1) return null;

  const { page, per_page, total, total_pages } = pagination;
  const from = (page - 1) * per_page + 1;
  const to = Math.min(page * per_page, total);

  return (
    <div className="flex items-center justify-between gap-3">
      <p className="text-sm tabular-nums text-muted-foreground">
        {total} kayıttan {from}–{to} arası gösteriliyor
      </p>
      <div className="flex items-center gap-1.5">
        <Button
          variant="outline"
          size="icon-sm"
          disabled={page <= 1}
          onClick={() => onPageChange(page - 1)}
          aria-label="Önceki sayfa"
        >
          <ChevronLeft />
        </Button>
        <span className="px-1 text-sm tabular-nums text-muted-foreground">
          {page} / {total_pages}
        </span>
        <Button
          variant="outline"
          size="icon-sm"
          disabled={page >= total_pages}
          onClick={() => onPageChange(page + 1)}
          aria-label="Sonraki sayfa"
        >
          <ChevronRight />
        </Button>
      </div>
    </div>
  );
}
