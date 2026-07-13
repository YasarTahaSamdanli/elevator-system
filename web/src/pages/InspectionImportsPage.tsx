import * as React from "react";
import { AlertTriangle, FileText, Hammer, Inbox, Loader2, Upload } from "lucide-react";
import { PageHeader } from "@/components/common/PageHeader";
import { Toolbar } from "@/components/common/Toolbar";
import { SearchInput } from "@/components/common/SearchInput";
import { ALL_VALUE, FilterSelect } from "@/components/common/FilterSelect";
import { DataTable, type Column } from "@/components/common/DataTable";
import { EmptyState } from "@/components/common/EmptyState";
import { ListError } from "@/components/common/ListError";
import { Pagination } from "@/components/common/Pagination";
import { StatusBadge } from "@/components/common/StatusBadge";
import { Field, FormErrorBanner } from "@/components/common/Field";
import { ConfirmDeleteDialog, useConfirmDelete } from "@/components/common/ConfirmDeleteDialog";
import { RowActionsMenu } from "@/components/common/RowActionsMenu";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { DropdownMenuItem } from "@/components/ui/dropdown-menu";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  inspectionImportReviewReasonLabel,
  inspectionImportStatusMeta,
  inspectionLabelMeta,
} from "@/lib/status";
import { formatDate } from "@/lib/format";
import {
  createInspectionWorkOrder,
  deleteInspectionImport,
  fetchElevators,
  fetchInspectionImportPdf,
  fetchInspectionImports,
  ignoreInspectionImport,
  matchInspectionImport,
  retryInspectionImport,
  uploadInspectionImport,
} from "@/api/resources";
import { useDebounced, useList } from "@/hooks/useList";
import { ApiError } from "@/lib/api";
import type { Elevator, InspectionImport, InspectionImportStatus } from "@/types";

const statusOptions = (
  Object.keys(inspectionImportStatusMeta) as InspectionImportStatus[]
).map((s) => ({ value: s, label: inspectionImportStatusMeta[s].label }));

const columns: Column<InspectionImport>[] = [
  {
    key: "report",
    header: "Rapor",
    cell: (i) => (
      <div className="min-w-0">
        <div className="truncate font-medium text-foreground">
          {i.mail_subject ?? i.original_filename ?? "—"}
        </div>
        <div className="truncate text-xs text-muted-foreground">
          {i.source === "email" ? (i.mail_from ?? "e-posta") : "Elle yüklendi"}
          {i.report_number ? ` · ${i.report_number}` : ""}
        </div>
      </div>
    ),
  },
  {
    key: "received",
    header: "Alındı",
    hideOnMobile: true,
    sortAccessor: (i) => i.mail_received_at ?? i.created_at,
    cell: (i) => (
      <span className="tabular-nums">{formatDate(i.mail_received_at ?? i.created_at)}</span>
    ),
  },
  {
    key: "label",
    header: "Etiket",
    cell: (i) =>
      i.parsed_label ? (
        <StatusBadge meta={inspectionLabelMeta[i.parsed_label]} />
      ) : (
        <span className="text-muted-foreground">—</span>
      ),
  },
  {
    key: "match",
    header: "Asansör",
    hideOnMobile: true,
    cell: (i) =>
      i.elevator_name ? (
        <div className="min-w-0">
          <div className="truncate">{i.elevator_name}</div>
          <div className="truncate text-xs text-muted-foreground">{i.building_name}</div>
        </div>
      ) : (
        <span className="text-muted-foreground">{i.parsed_identity ?? "—"}</span>
      ),
  },
  {
    key: "status",
    header: "Durum",
    sortAccessor: (i) => i.status,
    cell: (i) => (
      <div className="space-y-1">
        <StatusBadge meta={inspectionImportStatusMeta[i.status]} />
        {i.review_reason && (
          <div className="text-xs text-muted-foreground">
            {inspectionImportReviewReasonLabel[i.review_reason]}
          </div>
        )}
        {i.work_order_error && (
          <div className="flex items-center gap-1 text-xs text-warning-foreground">
            <AlertTriangle className="size-3 shrink-0" />
            İş emri açılamadı
          </div>
        )}
      </div>
    ),
  },
  {
    key: "work_order",
    header: "İş Emri",
    hideOnMobile: true,
    cell: (i) =>
      i.inspection?.work_order ? (
        <span className="font-mono text-xs">{i.inspection.work_order.work_order_number}</span>
      ) : (
        <span className="text-muted-foreground">—</span>
      ),
  },
];

function MatchDialog({
  open,
  target,
  elevators,
  isSubmitting,
  error,
  onOpenChange,
  onSubmit,
}: {
  open: boolean;
  target: InspectionImport | null;
  elevators: Elevator[];
  isSubmitting: boolean;
  error: string | null;
  onOpenChange: (open: boolean) => void;
  onSubmit: (elevatorUuid: string) => Promise<void>;
}) {
  const [elevatorUuid, setElevatorUuid] = React.useState("");

  React.useEffect(() => {
    if (open) setElevatorUuid("");
  }, [open]);

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>Raporu Asansörle Eşleştir</DialogTitle>
          <DialogDescription>
            {target?.mail_subject ?? target?.original_filename ?? "Rapor"} bu asansöre bağlanacak
            ve sistem bu eşleşmeyi öğrenecek — aynı bina adıyla gelen sonraki raporlar otomatik
            eşleşir.
          </DialogDescription>
        </DialogHeader>

        <FormErrorBanner message={error} />

        {target?.parsed_findings.length ? (
          <div className="rounded-md border border-border bg-muted/40 p-3 text-xs text-muted-foreground">
            <div className="mb-1 font-medium text-foreground">Rapordaki kusurlar</div>
            <ul className="list-inside list-disc space-y-0.5">
              {target.parsed_findings.map((f, idx) => (
                <li key={idx}>{f}</li>
              ))}
            </ul>
          </div>
        ) : null}

        <Field label="Asansör">
          <Select value={elevatorUuid || undefined} onValueChange={setElevatorUuid}>
            <SelectTrigger>
              <SelectValue placeholder="Asansör seç" />
            </SelectTrigger>
            <SelectContent>
              {elevators.map((elevator) => (
                <SelectItem key={elevator.id} value={elevator.id}>
                  {(elevator.name ?? elevator.serial_number) + " · " + elevator.building_name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </Field>

        <DialogFooter>
          <Button
            type="button"
            variant="outline"
            disabled={isSubmitting}
            onClick={() => onOpenChange(false)}
          >
            Vazgeç
          </Button>
          <Button
            type="button"
            disabled={!elevatorUuid || isSubmitting}
            onClick={() => void onSubmit(elevatorUuid)}
          >
            {isSubmitting && <Loader2 className="animate-spin" />}
            Eşleştir ve İçe Aktar
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

export function InspectionImportsPage() {
  const [query, setQuery] = React.useState("");
  const [status, setStatus] = React.useState<string>("needs_review");
  const [page, setPage] = React.useState(1);
  const [pageError, setPageError] = React.useState<string | null>(null);
  const [busyRow, setBusyRow] = React.useState<string | null>(null);
  const [isUploading, setIsUploading] = React.useState(false);
  const [matchTarget, setMatchTarget] = React.useState<InspectionImport | null>(null);
  const [matchError, setMatchError] = React.useState<string | null>(null);
  const [isMatching, setIsMatching] = React.useState(false);
  const del = useConfirmDelete<InspectionImport>();
  const fileInputRef = React.useRef<HTMLInputElement>(null);
  const debouncedQuery = useDebounced(query);

  React.useEffect(() => {
    setPage(1);
  }, [debouncedQuery, status]);

  const listParams = React.useMemo(
    () => ({
      page,
      perPage: 25,
      search: debouncedQuery,
      sort: "-created_at",
      filter: { ...(status === ALL_VALUE ? {} : { status }) },
    }),
    [page, debouncedQuery, status]
  );
  const elevatorParams = React.useMemo(() => ({ perPage: 100, sort: "name" }), []);
  const { items: imports, pagination, isLoading, error, reload } = useList(
    fetchInspectionImports,
    listParams
  );
  const { items: elevatorOptions } = useList(fetchElevators, elevatorParams);

  const run = async (importId: string, action: () => Promise<unknown>, fallback: string) => {
    setPageError(null);
    setBusyRow(importId);
    try {
      await action();
      reload();
    } catch (err) {
      setPageError(err instanceof ApiError ? err.message : fallback);
    } finally {
      setBusyRow(null);
    }
  };

  const handleUpload = async (file: File) => {
    setPageError(null);
    setIsUploading(true);
    try {
      await uploadInspectionImport(file);
      reload();
    } catch (err) {
      setPageError(err instanceof ApiError ? err.message : "PDF yüklenirken bir hata oluştu.");
    } finally {
      setIsUploading(false);
      if (fileInputRef.current) fileInputRef.current.value = "";
    }
  };

  const handleOpenPdf = (imp: InspectionImport) =>
    run(
      imp.id,
      async () => {
        const blob = await fetchInspectionImportPdf(imp.id);
        window.open(URL.createObjectURL(blob), "_blank", "noopener");
      },
      "PDF açılamadı."
    );

  const handleMatch = async (elevatorUuid: string) => {
    if (!matchTarget) return;
    setMatchError(null);
    setIsMatching(true);
    try {
      await matchInspectionImport(matchTarget.id, elevatorUuid);
      setMatchTarget(null);
      reload();
    } catch (err) {
      setMatchError(err instanceof ApiError ? err.message : "Eşleştirme başarısız oldu.");
    } finally {
      setIsMatching(false);
    }
  };

  return (
    <div className="space-y-5">
      <PageHeader
        title="Gelen Raporlar"
        description="RoyalCert muayene raporları — mailden otomatik alınır, eşleşmeyenler burada elle bağlanır"
        count={pagination?.total ?? imports.length}
        actions={
          <>
            <input
              ref={fileInputRef}
              type="file"
              accept="application/pdf"
              className="hidden"
              onChange={(event) => {
                const file = event.target.files?.[0];
                if (file) void handleUpload(file);
              }}
            />
            <Button disabled={isUploading} onClick={() => fileInputRef.current?.click()}>
              {isUploading ? <Loader2 className="animate-spin" /> : <Upload />}
              Rapor Yükle
            </Button>
          </>
        }
      />

      <Toolbar>
        <SearchInput
          value={query}
          onChange={setQuery}
          placeholder="Konu, gönderen veya rapor no ara..."
        />
        <FilterSelect
          value={status}
          onChange={setStatus}
          allLabel="Tüm Durumlar"
          options={statusOptions}
        />
      </Toolbar>

      {error && <ListError message={error.message} onRetry={reload} />}
      <FormErrorBanner message={pageError} />

      <DataTable
        columns={columns}
        data={imports}
        getRowId={(i) => i.id}
        isLoading={isLoading}
        rowActions={(imp) => (
          <RowActionsMenu
            ariaLabel="Rapor işlemleri"
            icon={busyRow === imp.id ? <Loader2 className="animate-spin" /> : undefined}
            onDelete={() => del.request(imp)}
          >
            <DropdownMenuItem onSelect={() => void handleOpenPdf(imp)}>
              <FileText className="size-4" />
              PDF&apos;i Aç
            </DropdownMenuItem>
            {imp.status === "needs_review" && (
              <DropdownMenuItem
                onSelect={() => {
                  setMatchError(null);
                  setMatchTarget(imp);
                }}
              >
                <Inbox className="size-4" />
                Asansörle Eşleştir
              </DropdownMenuItem>
            )}
            {(imp.status === "needs_review" || imp.status === "failed") && (
              <DropdownMenuItem
                onSelect={() =>
                  void run(imp.id, () => retryInspectionImport(imp.id), "Yeniden deneme başarısız.")
                }
              >
                <Loader2 className="size-4" />
                Yeniden Dene
              </DropdownMenuItem>
            )}
            {imp.status === "imported" && imp.work_order_error && imp.inspection && (
              <DropdownMenuItem
                onSelect={() =>
                  void run(
                    imp.id,
                    () => createInspectionWorkOrder(imp.inspection!.id),
                    "İş emri oluşturulamadı."
                  )
                }
              >
                <Hammer className="size-4" />
                Revizyon İş Emri Aç
              </DropdownMenuItem>
            )}
            {imp.status !== "imported" && imp.status !== "ignored" && (
              <DropdownMenuItem
                onSelect={() =>
                  void run(imp.id, () => ignoreInspectionImport(imp.id), "Rapor yoksayılamadı.")
                }
              >
                Yoksay
              </DropdownMenuItem>
            )}
          </RowActionsMenu>
        )}
        empty={
          <EmptyState
            icon={Inbox}
            title={status === "needs_review" ? "İnceleme bekleyen rapor yok" : "Rapor bulunamadı"}
            description={
              status === "needs_review"
                ? "Tüm gelen raporlar otomatik eşleşti. Diğer kayıtlar için durum filtresini değiştir."
                : "Arama veya filtre kriterlerine uyan rapor kaydı yok."
            }
          />
        }
      />

      <Pagination pagination={pagination} onPageChange={setPage} />

      <MatchDialog
        open={!!matchTarget}
        target={matchTarget}
        elevators={elevatorOptions}
        isSubmitting={isMatching}
        error={matchError}
        onOpenChange={(open) => {
          if (!open) setMatchTarget(null);
        }}
        onSubmit={handleMatch}
      />

      <ConfirmDeleteDialog
        open={!!del.target}
        title="Rapor Kaydını Sil"
        description={
          del.target
            ? `${del.target.mail_subject ?? del.target.original_filename ?? "Rapor"} kaydı silinecek. Oluşturulmuş kontrol kaydı ve iş emri etkilenmez.`
            : ""
        }
        error={del.error}
        isDeleting={del.isDeleting}
        onClose={del.close}
        onConfirm={() =>
          void del.confirm(async () => {
            if (!del.target) return;
            await deleteInspectionImport(del.target.id);
            reload();
          })
        }
      />
    </div>
  );
}
