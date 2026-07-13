import * as React from "react";
import { Loader2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { ApiError } from "@/lib/api";

/**
 * Tracks which record a delete confirmation is open for. `confirm` runs the
 * delete call, closes on success and surfaces the API error message on
 * failure (shown by ConfirmDeleteDialog).
 */
export function useConfirmDelete<T>() {
  const [target, setTarget] = React.useState<T | null>(null);
  const [isDeleting, setDeleting] = React.useState(false);
  const [error, setError] = React.useState<string | null>(null);

  const request = (record: T) => {
    setError(null);
    setTarget(record);
  };

  const close = () => setTarget(null);

  const confirm = async (remove: () => Promise<void>) => {
    setDeleting(true);
    setError(null);

    try {
      await remove();
      setTarget(null);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Beklenmeyen bir hata oluştu.");
    } finally {
      setDeleting(false);
    }
  };

  return { target, isDeleting, error, request, close, confirm };
}

export function ConfirmDeleteDialog({
  open,
  title,
  description,
  error,
  isDeleting,
  onClose,
  onConfirm,
}: {
  open: boolean;
  title: string;
  description: React.ReactNode;
  error?: string | null;
  isDeleting: boolean;
  onClose: () => void;
  onConfirm: () => void;
}) {
  return (
    <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{title}</DialogTitle>
          <DialogDescription>{description}</DialogDescription>
        </DialogHeader>
        {error && (
          <div className="rounded-md bg-danger-subtle px-3 py-2 text-sm text-danger-foreground">
            {error}
          </div>
        )}
        <DialogFooter>
          <Button variant="outline" disabled={isDeleting} onClick={onClose}>
            Vazgeç
          </Button>
          <Button variant="destructive" disabled={isDeleting} onClick={onConfirm}>
            {isDeleting && <Loader2 className="animate-spin" />}
            Sil
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
