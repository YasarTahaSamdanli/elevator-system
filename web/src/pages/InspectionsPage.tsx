import * as React from "react";
import { ClipboardCheck, Hammer, Loader2, Plus, X } from "lucide-react";
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
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { inspectionLabelMeta, inspectionTypeMeta } from "@/lib/status";
import { formatDate } from "@/lib/format";
import { blankToNull, fieldError, metaOptions, todayIso } from "@/lib/forms";
import {
  createInspection,
  createInspectionWorkOrder,
  deleteInspection,
  fetchBuildings,
  fetchElevators,
  fetchInspections,
  updateInspection,
  type InspectionInput,
} from "@/api/resources";
import { useDebounced, useList } from "@/hooks/useList";
import { useFormDialog } from "@/hooks/useFormDialog";
import { ApiError } from "@/lib/api";
import type { Elevator, ElevatorInspection, InspectionLabel, InspectionType } from "@/types";

const labelOptions = (Object.keys(inspectionLabelMeta) as InspectionLabel[]).map((l) => ({
  value: l,
  label: `${inspectionLabelMeta[l].label} Etiket`,
}));

const typeOptions = metaOptions<InspectionType>(inspectionTypeMeta);

const columns: Column<ElevatorInspection>[] = [
  {
    key: "elevator",
    header: "Asansör",
    cell: (i) => (
      <div className="min-w-0">
        <div className="font-medium text-foreground">{i.elevator_name}</div>
        <div className="text-xs text-muted-foreground">{i.building_name}</div>
      </div>
    ),
  },
  {
    key: "type",
    header: "Tip",
    hideOnMobile: true,
    sortAccessor: (i) => i.type,
    cell: (i) => <StatusBadge meta={inspectionTypeMeta[i.type]} />,
  },
  {
    key: "inspected_at",
    header: "Kontrol Tarihi",
    sortAccessor: (i) => i.inspected_at,
    cell: (i) => <span className="tabular-nums">{formatDate(i.inspected_at)}</span>,
  },
  {
    key: "label",
    header: "Etiket",
    sortAccessor: (i) => i.label,
    cell: (i) => <StatusBadge meta={inspectionLabelMeta[i.label]} />,
  },
  {
    key: "findings",
    header: "Bulgular",
    hideOnMobile: true,
    cell: (i) => {
      if (i.findings.length === 0) return <span className="text-muted-foreground">—</span>;
      const resolved = i.findings.filter((f) => f.is_resolved).length;
      return (
        <span className="tabular-nums text-muted-foreground">
          {resolved}/{i.findings.length} çözüldü
        </span>
      );
    },
  },
  {
    key: "follow_up",
    header: "Takip Tarihi",
    hideOnMobile: true,
    sortAccessor: (i) => i.follow_up_due_date,
    cell: (i) => {
      if (!i.follow_up_due_date) return <span className="text-muted-foreground">—</span>;
      const overdue = i.follow_up_due_date < todayIso() && i.findings.some((f) => !f.is_resolved);
      return (
        <span className={overdue ? "font-medium text-danger-foreground" : "tabular-nums"}>
          {formatDate(i.follow_up_due_date)}
          {overdue ? " !" : ""}
        </span>
      );
    },
  },
  {
    key: "next",
    header: "Sonraki Periyodik",
    hideOnMobile: true,
    sortAccessor: (i) => i.next_inspection_date,
    cell: (i) =>
      i.next_inspection_date ? (
        <span className="tabular-nums">{formatDate(i.next_inspection_date)}</span>
      ) : (
        <span className="text-muted-foreground">—</span>
      ),
  },
  {
    key: "work_order",
    header: "İş Emri",
    hideOnMobile: true,
    cell: (i) =>
      i.work_order ? (
        <span className="font-mono text-xs">{i.work_order.work_order_number}</span>
      ) : (
        <span className="text-muted-foreground">—</span>
      ),
  },
];

interface FindingFormValue {
  id: string | null;
  description: string;
  is_resolved: boolean;
}

interface InspectionFormValues {
  elevator_uuid: string;
  type: InspectionType;
  inspection_body: string;
  inspected_at: string;
  label: InspectionLabel;
  report_number: string;
  follow_up_due_date: string;
  next_inspection_date: string;
  notes: string;
  findings: FindingFormValue[];
}

const emptyForm: InspectionFormValues = {
  elevator_uuid: "",
  type: "periodic",
  inspection_body: "",
  inspected_at: todayIso(),
  label: "green",
  report_number: "",
  follow_up_due_date: "",
  next_inspection_date: "",
  notes: "",
  findings: [],
};

function formFromInspection(inspection: ElevatorInspection | null): InspectionFormValues {
  if (!inspection) return emptyForm;

  return {
    elevator_uuid: inspection.elevator_id,
    type: inspection.type,
    inspection_body: inspection.inspection_body ?? "",
    inspected_at: inspection.inspected_at,
    label: inspection.label,
    report_number: inspection.report_number ?? "",
    follow_up_due_date: inspection.follow_up_due_date ?? "",
    next_inspection_date: inspection.next_inspection_date ?? "",
    notes: inspection.notes ?? "",
    findings: inspection.findings.map((f) => ({
      id: f.id,
      description: f.description,
      is_resolved: f.is_resolved,
    })),
  };
}

function formToInput(values: InspectionFormValues): InspectionInput {
  return {
    elevator_uuid: values.elevator_uuid,
    type: values.type,
    inspection_body: blankToNull(values.inspection_body),
    inspected_at: values.inspected_at,
    label: values.label,
    report_number: blankToNull(values.report_number),
    follow_up_due_date: blankToNull(values.follow_up_due_date),
    next_inspection_date: blankToNull(values.next_inspection_date),
    notes: blankToNull(values.notes),
    findings: values.findings
      .filter((f) => f.description.trim() !== "")
      .map((f) => ({ description: f.description.trim(), is_resolved: f.is_resolved })),
  };
}

function InspectionFormDialog({
  open,
  inspection,
  elevators,
  errors,
  formError,
  isSubmitting,
  onOpenChange,
  onSubmit,
}: {
  open: boolean;
  inspection: ElevatorInspection | null;
  elevators: Elevator[];
  errors: Record<string, string[]>;
  formError: string | null;
  isSubmitting: boolean;
  onOpenChange: (open: boolean) => void;
  onSubmit: (values: InspectionFormValues) => Promise<void>;
}) {
  const [values, setValues] = React.useState<InspectionFormValues>(() =>
    formFromInspection(inspection)
  );
  const isEditing = !!inspection;

  React.useEffect(() => {
    if (open) setValues(formFromInspection(inspection));
  }, [inspection, open]);

  const setValue = <K extends keyof InspectionFormValues>(
    field: K,
    value: InspectionFormValues[K]
  ) => {
    setValues((prev) => ({ ...prev, [field]: value }));
  };

  const setFinding = (index: number, patch: Partial<FindingFormValue>) => {
    setValues((prev) => ({
      ...prev,
      findings: prev.findings.map((f, i) => (i === index ? { ...f, ...patch } : f)),
    }));
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent
        className="max-h-[90vh] overflow-y-auto sm:max-w-2xl"
        onInteractOutside={(event) => event.preventDefault()}
      >
        <DialogHeader>
          <DialogTitle>{isEditing ? "Kontrolü Düzenle" : "Yeni Periyodik Kontrol"}</DialogTitle>
          <DialogDescription>
            Muayene kuruluşunun verdiği etiketi ve kusurları gir. Takip tarihi boş bırakılırsa
            kırmızı etikette +30, sarı etikette +60 gün olarak otomatik hesaplanır.
          </DialogDescription>
        </DialogHeader>

        <form
          noValidate
          className="space-y-4"
          onSubmit={(event) => {
            event.preventDefault();
            void onSubmit(values);
          }}
        >
          <FormErrorBanner message={formError} />

          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="Asansör" error={fieldError(errors, "elevator_uuid")}>
              <Select
                value={values.elevator_uuid || undefined}
                onValueChange={(value) => setValue("elevator_uuid", value)}
                disabled={isEditing}
              >
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
            <Field label="Kontrol Tipi" error={fieldError(errors, "type")}>
              <Select
                value={values.type}
                onValueChange={(value) => setValue("type", value as InspectionType)}
              >
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {typeOptions.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                      {option.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </Field>
          </div>

          <div className="grid gap-4 sm:grid-cols-3">
            <Field label="Kontrol Tarihi" error={fieldError(errors, "inspected_at")}>
              <Input
                type="date"
                value={values.inspected_at}
                onChange={(event) => setValue("inspected_at", event.target.value)}
                required
              />
            </Field>
            <Field label="Etiket" error={fieldError(errors, "label")}>
              <Select
                value={values.label}
                onValueChange={(value) => setValue("label", value as InspectionLabel)}
              >
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {labelOptions.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                      {option.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </Field>
            <Field label="Rapor No" error={fieldError(errors, "report_number")}>
              <Input
                value={values.report_number}
                onChange={(event) => setValue("report_number", event.target.value)}
              />
            </Field>
          </div>

          <div className="grid gap-4 sm:grid-cols-3">
            <Field label="Muayene Kuruluşu" error={fieldError(errors, "inspection_body")}>
              <Input
                value={values.inspection_body}
                onChange={(event) => setValue("inspection_body", event.target.value)}
                placeholder="Örn: TSE"
              />
            </Field>
            <Field label="Takip Tarihi" error={fieldError(errors, "follow_up_due_date")}>
              <Input
                type="date"
                value={values.follow_up_due_date}
                onChange={(event) => setValue("follow_up_due_date", event.target.value)}
              />
            </Field>
            <Field label="Sonraki Periyodik" error={fieldError(errors, "next_inspection_date")}>
              <Input
                type="date"
                value={values.next_inspection_date}
                onChange={(event) => setValue("next_inspection_date", event.target.value)}
              />
            </Field>
          </div>

          <div className="space-y-2">
            <div className="flex items-center justify-between">
              <span className="text-sm font-medium text-foreground">Kusurlar / Bulgular</span>
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() =>
                  setValue("findings", [
                    ...values.findings,
                    { id: null, description: "", is_resolved: false },
                  ])
                }
              >
                <Plus />
                Bulgu Ekle
              </Button>
            </div>
            {values.findings.length === 0 && (
              <p className="text-xs text-muted-foreground">
                Kusur yoksa boş bırak (yeşil etiket). Kırmızı/sarı etikette rapor kusurlarını
                buraya gir — iş emri açıldığında kontrol listesine kopyalanır.
              </p>
            )}
            {values.findings.map((finding, index) => (
              <div key={index} className="flex items-center gap-2">
                <Input
                  value={finding.description}
                  onChange={(event) => setFinding(index, { description: event.target.value })}
                  placeholder={`Kusur ${index + 1}`}
                />
                {isEditing && (
                  <label className="flex shrink-0 items-center gap-1.5 text-xs text-muted-foreground">
                    <input
                      type="checkbox"
                      checked={finding.is_resolved}
                      onChange={(event) => setFinding(index, { is_resolved: event.target.checked })}
                    />
                    Çözüldü
                  </label>
                )}
                <Button
                  type="button"
                  variant="ghost"
                  size="icon-sm"
                  aria-label="Bulguyu kaldır"
                  onClick={() =>
                    setValue(
                      "findings",
                      values.findings.filter((_, i) => i !== index)
                    )
                  }
                >
                  <X />
                </Button>
              </div>
            ))}
            {fieldError(errors, "findings") && (
              <span className="block text-xs text-danger-foreground">
                {fieldError(errors, "findings")}
              </span>
            )}
          </div>

          <Field label="Notlar" error={fieldError(errors, "notes")}>
            <Textarea
              value={values.notes}
              onChange={(event) => setValue("notes", event.target.value)}
            />
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
            <Button type="submit" disabled={isSubmitting}>
              {isSubmitting && <Loader2 className="animate-spin" />}
              {isEditing ? "Kaydet" : "Kontrolü Kaydet"}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

export function InspectionsPage() {
  const [query, setQuery] = React.useState("");
  const [label, setLabel] = React.useState(ALL_VALUE);
  const [type, setType] = React.useState(ALL_VALUE);
  const [building, setBuilding] = React.useState(ALL_VALUE);
  const [page, setPage] = React.useState(1);
  const form = useFormDialog<ElevatorInspection>();
  const del = useConfirmDelete<ElevatorInspection>();
  const [pageError, setPageError] = React.useState<string | null>(null);
  const [workOrderTarget, setWorkOrderTarget] = React.useState<string | null>(null);
  const debouncedQuery = useDebounced(query);

  React.useEffect(() => {
    setPage(1);
  }, [debouncedQuery, label, type, building]);

  const listParams = React.useMemo(
    () => ({
      page,
      perPage: 25,
      search: debouncedQuery,
      sort: "-inspected_at",
      filter: {
        ...(label === ALL_VALUE ? {} : { label }),
        ...(type === ALL_VALUE ? {} : { type }),
        ...(building === ALL_VALUE ? {} : { building_uuid: building }),
      },
    }),
    [page, debouncedQuery, label, type, building]
  );
  const buildingParams = React.useMemo(() => ({ perPage: 100, sort: "name" }), []);
  const elevatorParams = React.useMemo(() => ({ perPage: 100, sort: "name" }), []);
  const { items: inspections, pagination, isLoading, error, reload } = useList(
    fetchInspections,
    listParams
  );
  const { items: buildingOptions } = useList(fetchBuildings, buildingParams);
  const { items: elevatorOptions } = useList(fetchElevators, elevatorParams);

  const handleSubmit = (values: InspectionFormValues) =>
    form.submit(async () => {
      const input = formToInput(values);
      if (form.editing) {
        await updateInspection(form.editing.id, input);
      } else {
        await createInspection(input);
      }
      reload();
    });

  const handleCreateWorkOrder = async (inspection: ElevatorInspection) => {
    setPageError(null);
    setWorkOrderTarget(inspection.id);

    try {
      await createInspectionWorkOrder(inspection.id);
      reload();
    } catch (err) {
      setPageError(
        err instanceof ApiError ? err.message : "İş emri oluşturulurken bir hata oluştu."
      );
    } finally {
      setWorkOrderTarget(null);
    }
  };

  return (
    <div className="space-y-5">
      <PageHeader
        title="Periyodik Kontroller"
        description="Muayene kuruluşu etiketleri ve kusur takibi"
        count={pagination?.total ?? inspections.length}
        actions={
          <Button onClick={form.openCreate}>
            <Plus />
            Yeni Kontrol
          </Button>
        }
      />

      <Toolbar>
        <SearchInput
          value={query}
          onChange={setQuery}
          placeholder="Rapor no veya kuruluş ara..."
        />
        <FilterSelect
          value={label}
          onChange={setLabel}
          allLabel="Tüm Etiketler"
          options={labelOptions}
        />
        <FilterSelect value={type} onChange={setType} allLabel="Tüm Tipler" options={typeOptions} />
        <FilterSelect
          value={building}
          onChange={setBuilding}
          allLabel="Tüm Binalar"
          options={buildingOptions.map((b) => ({ value: b.id, label: b.name }))}
        />
      </Toolbar>

      {error && <ListError message={error.message} onRetry={reload} />}
      <FormErrorBanner message={pageError} />

      <DataTable
        columns={columns}
        data={inspections}
        getRowId={(i) => i.id}
        isLoading={isLoading}
        rowActions={(inspection) => (
          <RowActionsMenu
            ariaLabel="Kontrol işlemleri"
            icon={workOrderTarget === inspection.id ? <Loader2 className="animate-spin" /> : undefined}
            onEdit={() => form.openEdit(inspection)}
            onDelete={() => del.request(inspection)}
          >
            {!inspection.work_order && (
              <DropdownMenuItem onSelect={() => void handleCreateWorkOrder(inspection)}>
                <Hammer className="size-4" />
                Revizyon İş Emri Aç
              </DropdownMenuItem>
            )}
          </RowActionsMenu>
        )}
        empty={
          <EmptyState
            icon={ClipboardCheck}
            title="Kontrol kaydı bulunamadı"
            description="Arama veya filtre kriterlerine uyan periyodik kontrol yok."
          />
        }
      />

      <Pagination pagination={pagination} onPageChange={setPage} />

      <InspectionFormDialog
        open={form.open}
        inspection={form.editing}
        elevators={elevatorOptions}
        errors={form.errors}
        formError={form.formError}
        isSubmitting={form.isSubmitting}
        onOpenChange={form.onOpenChange}
        onSubmit={handleSubmit}
      />

      <ConfirmDeleteDialog
        open={!!del.target}
        title="Kontrol Kaydını Sil"
        description={
          del.target
            ? `${del.target.elevator_name} · ${formatDate(
                del.target.inspected_at
              )} tarihli kontrol kaydı silinecek. Asansörün etiket durumu bir önceki kontrole göre yeniden hesaplanır.`
            : ""
        }
        error={del.error}
        isDeleting={del.isDeleting}
        onClose={del.close}
        onConfirm={() =>
          void del.confirm(async () => {
            if (!del.target) return;
            await deleteInspection(del.target.id);
            reload();
          })
        }
      />
    </div>
  );
}
