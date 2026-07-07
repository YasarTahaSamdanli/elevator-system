import { AlertTriangle } from "lucide-react";
import { Button } from "@/components/ui/button";

interface ListErrorProps {
  message: string;
  onRetry?: () => void;
}

export function ListError({ message, onRetry }: ListErrorProps) {
  return (
    <div className="flex items-center justify-between gap-3 rounded-md border border-danger/25 bg-danger-subtle px-4 py-3 text-sm text-danger-foreground">
      <div className="flex min-w-0 items-center gap-2">
        <AlertTriangle className="size-4 shrink-0" />
        <span className="truncate">{message}</span>
      </div>
      {onRetry && (
        <Button type="button" variant="outline" size="sm" onClick={onRetry}>
          Tekrar Dene
        </Button>
      )}
    </div>
  );
}
