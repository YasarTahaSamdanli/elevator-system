import * as React from "react";
import { ArrowUpDown, Loader2, Plus, QrCode } from "lucide-react";
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
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip";
import { elevatorStatusMeta, inspectionLabelMeta } from "@/lib/status";
import { formatDate, formatNumber } from "@/lib/format";
import { blankToNull, fieldError, metaOptions, numericOrNull } from "@/lib/forms";
import {
  createElevator,
  deleteElevator,
  fetchBuildings,
  fetchElevators,
  updateElevator,
  type ElevatorInput,
} from "@/api/resources";
import { useDebounced, useList } from "@/hooks/useList";
import { useFormDialog } from "@/hooks/useFormDialog";
import type { Building, Elevator, ElevatorStatus } from "@/types";

const columns: Column<Elevator>[] = [
  {
    key: "name",
    header: "Asansör",
    sortAccessor: (e) => e.name ?? e.serial_number,
    cell: (e) => (
      <div className="min-w-0">
        <div className="flex items-center gap-1.5 font-medium text-foreground">
          {e.name ?? e.serial_number}
          <Tooltip>
            <TooltipTrigger asChild>
              <QrCode className="size-3.5 shrink-0 text-muted-foreground" />
            </TooltipTrigger>
            <TooltipContent className="font-mono text-xs">{e.qr_identifier}</TooltipContent>
          </Tooltip>
        </div>
        <div className="text-xs text-muted-foreground">{e.building_name}</div>
      </div>
    ),
  },
  {
    key: "serial",
    header: "Seri No",
    hideOnMobile: true,
    sortAccessor: (e) => e.serial_number,
    cell: (e) => <span className="font-mono text-xs">{e.serial_number}</span>,
  },
  {
    key: "make",
    header: "Üretici / Model",
    hideOnMobile: true,
    cell: (e) => (
      <div className="min-w-0">
        <div className="text-foreground">{e.manufacturer ?? "—"}</div>
        <div className="text-xs text-muted-foreground">{e.model ?? ""}</div>
      </div>
    ),
  },
  {
    key: "capacity",
    header: "Kapasite",
    align: "right",
    hideOnMobile: true,
    sortAccessor: (e) => e.capacity_kg,
    cell: (e) => (
      <span className="tabular-nums text-muted-foreground">
        {e.capacity_kg ? `${formatNumber(e.capacity_kg)} kg` : "—"}
        {e.person_capacity ? ` · ${e.person_capacity} kişi` : ""}
      </span>
    ),
  },
  {
    key: "stops",
    header: "Durak",
    align: "right",
    hideOnMobile: true,
    sortAccessor: (e) => e.stop_count,
    cell: (e) => <span className="tabular-nums">{e.stop_count ?? "—"}</span>,
  },
  {
    key: "status",
    header: "Durum",
    sortAccessor: (e) => e.status,
    cell: (e) => <StatusBadge meta={elevatorStatusMeta[e.status]} />,
  },
  {
    key: "label",
    header: "Etiket",
    sortAccessor: (e) => e.current_label,
    cell: (e) =>
      e.current_label ? (
        <Tooltip>
          <TooltipTrigger asChild>
            <span>
              <StatusBadge meta={inspectionLabelMeta[e.current_label]} />
            </span>
          </TooltipTrigger>
          <TooltipContent className="text-xs">
            {e.last_inspection_at ? `Son kontrol: ${formatDate(e.last_inspection_at)}` : ""}
            {e.follow_up_due ? ` · Takip: ${formatDate(e.follow_up_due)}` : ""}
            {e.next_inspection_due ? ` · Sonraki: ${formatDate(e.next_inspection_due)}` : ""}
          </TooltipContent>
        </Tooltip>
      ) : (
        <span className="text-muted-foreground">—</span>
      ),
  },
];

const statusOptions = metaOptions<ElevatorStatus>(elevatorStatusMeta);

interface ElevatorFormValues {
  building_uuid: string;
  serial_number: string;
  name: string;
  manufacturer: string;
  model: string;
  installation_year: string;
  capacity_kg: string;
  person_capacity: string;
  stop_count: string;
  registration_number: string;
  status: ElevatorStatus;
  notes: string;
}

const emptyForm: ElevatorFormValues = {
  building_uuid: "",
  serial_number: "",
  name: "",
  manufacturer: "",
  model: "",
  installation_year: "",
  capacity_kg: "",
  person_capacity: "",
  stop_count: "",
  registration_number: "",
  status: "active",
  notes: "",
};

function formFromElevator(elevator: Elevator | null): ElevatorFormValues {
  if (!elevator) return emptyForm;

  return {
    building_uuid: elevator.building_id,
    serial_number: elevator.serial_number,
    name: elevator.name ?? "",
    manufacturer: elevator.manufacturer ?? "",
    model: elevator.model ?? "",
    installation_year: elevator.installation_year == null ? "" : String(elevator.installation_year),
    capacity_kg: elevator.capacity_kg == null ? "" : String(elevator.capacity_kg),
    person_capacity: elevator.person_capacity == null ? "" : String(elevator.person_capacity),
    stop_count: elevator.stop_count == null ? "" : String(elevator.stop_count),
    registration_number: elevator.registration_number ?? "",
    status: elevator.status,
    notes: elevator.notes ?? "",
  };
}

function formToInput(values: ElevatorFormValues): ElevatorInput {
  return {
    building_uuid: values.building_uuid,
    serial_number: values.serial_number.trim(),
    name: blankToNull(values.name),
    manufacturer: blankToNull(values.manufacturer),
    model: blankToNull(values.model),
    installation_year: numericOrNull(values.installation_year),
    capacity_kg: numericOrNull(values.capacity_kg),
    person_capacity: numericOrNull(values.person_capacity),
    stop_count: numericOrNull(values.stop_count),
    registration_number: blankToNull(values.registration_number),
    status: values.status,
    notes: blankToNull(values.notes),
  };
}

function ElevatorFormDialog({
  open,
  elevator,
  buildings,
  errors,
  formError,
  isSubmitting,
  onOpenChange,
  onSubmit,
}: {
  open: boolean;
  elevator: Elevator | null;
  buildings: Building[];
  errors: Record<string, string[]>;
  formError: string | null;
  isSubmitting: boolean;
  onOpenChange: (open: boolean) => void;
  onSubmit: (values: ElevatorFormValues) => Promise<void>;
}) {
  const [values, setValues] = React.useState<ElevatorFormValues>(() => formFromElevator(elevator));
  const isEditing = !!elevator;

  React.useEffect(() => {
    if (open) setValues(formFromElevator(elevator));
  }, [elevator, open]);

  const setValue = (field: keyof ElevatorFormValues, value: string) => {
    setValues((prev) => ({ ...prev, [field]: value }));
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent
        className="max-h-[90vh] overflow-y-auto sm:max-w-2xl"
        onInteractOutside={(event) => event.preventDefault()}
      >
        <DialogHeader>
          <DialogTitle>{isEditing ? "Asansörü Düzenle" : "Yeni Asansör"}</DialogTitle>
          <DialogDescription>
            {isEditing ? (
              <>
                QR kimliği: <span className="font-mono text-xs">{elevator.qr_identifier}</span>
              </>
            ) : (
              "Asansörün bina, kimlik ve teknik bilgilerini gir. QR kimliği kayıt sırasında otomatik oluşturulur."
            )}
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
            <Field label="Bina" error={fieldError(errors, "building_uuid")}>
              <Select
                value={values.building_uuid || undefined}
                onValueChange={(value) => setValue("building_uuid", value)}
              >
                <SelectTrigger>
                  <SelectValue placeholder="Bina seç" />
                </SelectTrigger>
                <SelectContent>
                  {buildings.map((building) => (
                    <SelectItem key={building.id} value={building.id}>
                      {building.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </Field>
            <Field label="Seri No" error={fieldError(errors, "serial_number")}>
              <Input
                value={values.serial_number}
                onChange={(event) => setValue("serial_number", event.target.value)}
                required
              />
            </Field>
          </div>

          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="Ad (opsiyonel)" error={fieldError(errors, "name")}>
              <Input
                value={values.name}
                onChange={(event) => setValue("name", event.target.value)}
                placeholder="Örn: A Blok Yolcu Asansörü"
              />
            </Field>
            <Field label="Tescil No" error={fieldError(errors, "registration_number")}>
              <Input
                value={values.registration_number}
                onChange={(event) => setValue("registration_number", event.target.value)}
              />
            </Field>
          </div>

          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="Üretici" error={fieldError(errors, "manufacturer")}>
              <Input
                value={values.manufacturer}
                onChange={(event) => setValue("manufacturer", event.target.value)}
              />
            </Field>
            <Field label="Model" error={fieldError(errors, "model")}>
              <Input
                value={values.model}
                onChange={(event) => setValue("model", event.target.value)}
              />
            </Field>
          </div>

          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <Field label="Montaj Yılı" error={fieldError(errors, "installation_year")}>
              <Input
                type="number"
                value={values.installation_year}
                onChange={(event) => setValue("installation_year", event.target.value)}
              />
            </Field>
            <Field label="Kapasite (kg)" error={fieldError(errors, "capacity_kg")}>
              <Input
                type="number"
                min={1}
                value={values.capacity_kg}
                onChange={(event) => setValue("capacity_kg", event.target.value)}
              />
            </Field>
            <Field label="Kişi" error={fieldError(errors, "person_capacity")}>
              <Input
                type="number"
                min={1}
                value={values.person_capacity}
                onChange={(event) => setValue("person_capacity", event.target.value)}
              />
            </Field>
            <Field label="Durak" error={fieldError(errors, "stop_count")}>
              <Input
                type="number"
                min={1}
                value={values.stop_count}
                onChange={(event) => setValue("stop_count", event.target.value)}
              />
            </Field>
            <Field label="Durum" error={fieldError(errors, "status")}>
              <Select
                value={values.status}
                onValueChange={(value) => setValue("status", value)}
              >
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {statusOptions.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                      {option.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </Field>
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
              {isEditing ? "Kaydet" : "Asansör Ekle"}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

export function ElevatorsPage() {
  const [query, setQuery] = React.useState("");
  const [status, setStatus] = React.useState(ALL_VALUE);
  const [building, setBuilding] = React.useState(ALL_VALUE);
  const [page, setPage] = React.useState(1);
  const form = useFormDialog<Elevator>();
  const del = useConfirmDelete<Elevator>();
  const debouncedQuery = useDebounced(query);

  React.useEffect(() => {
    setPage(1);
  }, [debouncedQuery, status, building]);

  const listParams = React.useMemo(
    () => ({
      page,
      perPage: 25,
      search: debouncedQuery,
      sort: "name",
      filter: {
        ...(status === ALL_VALUE ? {} : { status }),
        ...(building === ALL_VALUE ? {} : { building_uuid: building }),
      },
    }),
    [page, debouncedQuery, status, building]
  );
  const buildingParams = React.useMemo(() => ({ perPage: 100, sort: "name" }), []);
  const { items: elevators, pagination, isLoading, error, reload } = useList(
    fetchElevators,
    listParams
  );
  const { items: buildingOptions } = useList(fetchBuildings, buildingParams);

  const handleSubmit = (values: ElevatorFormValues) =>
    form.submit(async () => {
      const input = formToInput(values);
      if (form.editing) {
        await updateElevator(form.editing.id, input);
      } else {
        await createElevator(input);
      }
      reload();
    });

  return (
    <div className="space-y-5">
      <PageHeader
        title="Asansörler"
        description="Bakım sözleşmeli asansör envanteri"
        count={pagination?.total ?? elevators.length}
        actions={
          <Button onClick={form.openCreate}>
            <Plus />
            Yeni Asansör
          </Button>
        }
      />

      <Toolbar>
        <SearchInput
          value={query}
          onChange={setQuery}
          placeholder="Seri no, QR veya bina ara..."
        />
        <FilterSelect
          value={status}
          onChange={setStatus}
          allLabel="Tüm Durumlar"
          options={statusOptions}
        />
        <FilterSelect
          value={building}
          onChange={setBuilding}
          allLabel="Tüm Binalar"
          options={buildingOptions.map((b) => ({ value: b.id, label: b.name }))}
        />
      </Toolbar>

      {error && <ListError message={error.message} onRetry={reload} />}

      <DataTable
        columns={columns}
        data={elevators}
        getRowId={(e) => e.id}
        isLoading={isLoading}
        rowActions={(elevator) => (
          <RowActionsMenu
            ariaLabel="Asansör işlemleri"
            onEdit={() => form.openEdit(elevator)}
            onDelete={() => del.request(elevator)}
          />
        )}
        empty={
          <EmptyState
            icon={ArrowUpDown}
            title="Asansör bulunamadı"
            description="Arama veya filtre kriterlerine uyan asansör yok."
          />
        }
      />

      <Pagination pagination={pagination} onPageChange={setPage} />

      <ElevatorFormDialog
        open={form.open}
        elevator={form.editing}
        buildings={buildingOptions}
        errors={form.errors}
        formError={form.formError}
        isSubmitting={form.isSubmitting}
        onOpenChange={form.onOpenChange}
        onSubmit={handleSubmit}
      />

      <ConfirmDeleteDialog
        open={!!del.target}
        title="Asansörü Sil"
        description={`${
          del.target ? del.target.name ?? del.target.serial_number : ""
        } kaydı silinecek. Bu işlem kaydı liste görünümünden kaldırır.`}
        error={del.error}
        isDeleting={del.isDeleting}
        onClose={del.close}
        onConfirm={() =>
          void del.confirm(async () => {
            if (!del.target) return;
            await deleteElevator(del.target.id);
            reload();
          })
        }
      />
    </div>
  );
}
