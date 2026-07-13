import * as React from "react";
import { FileText, Loader2, Plus } from "lucide-react";
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
import { Badge } from "@/components/ui/badge";
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
import { contractStatusMeta } from "@/lib/status";
import { daysUntil, formatCurrency, formatDate } from "@/lib/format";
import { blankToNull, fieldError, metaOptions, numericOrNull, toDateInput } from "@/lib/forms";
import {
  createContract,
  deleteContract,
  fetchContracts,
  fetchElevators,
  updateContract,
  type ContractInput,
} from "@/api/resources";
import { useDebounced, useList } from "@/hooks/useList";
import { useFormDialog } from "@/hooks/useFormDialog";
import type { ContractStatus, Elevator, ServiceContract } from "@/types";

const columns: Column<ServiceContract>[] = [
  {
    key: "number",
    header: "Sözleşme",
    sortAccessor: (c) => c.contract_number,
    cell: (c) => (
      <div className="min-w-0">
        <div className="font-mono text-xs text-foreground">{c.contract_number ?? "—"}</div>
        <div className="truncate text-xs text-muted-foreground">
          {c.building_name} · {c.elevator_name}
        </div>
      </div>
    ),
  },
  {
    key: "start",
    header: "Başlangıç",
    hideOnMobile: true,
    sortAccessor: (c) => c.start_date,
    cell: (c) => <span className="tabular-nums text-muted-foreground">{formatDate(c.start_date)}</span>,
  },
  {
    key: "end",
    header: "Bitiş",
    sortAccessor: (c) => c.end_date,
    cell: (c) => {
      const days = daysUntil(c.end_date);
      const expiringSoon = c.status === "active" && days >= 0 && days <= 30;
      return (
        <div className="flex items-center gap-2">
          <span className="tabular-nums">{formatDate(c.end_date)}</span>
          {expiringSoon && <Badge variant="warning">{days} gün kaldı</Badge>}
        </div>
      );
    },
  },
  {
    key: "fee",
    header: "Aylık Ücret",
    align: "right",
    hideOnMobile: true,
    sortAccessor: (c) => c.monthly_fee,
    cell: (c) => <span className="tabular-nums">{formatCurrency(c.monthly_fee)}</span>,
  },
  {
    key: "status",
    header: "Durum",
    sortAccessor: (c) => c.status,
    cell: (c) => <StatusBadge meta={contractStatusMeta[c.status]} />,
  },
];

const statusOptions = metaOptions<ContractStatus>(contractStatusMeta);

interface ContractFormValues {
  elevator_uuid: string;
  contract_number: string;
  start_date: string;
  end_date: string;
  status: ContractStatus;
  monthly_fee: string;
  notes: string;
}

const emptyForm: ContractFormValues = {
  elevator_uuid: "",
  contract_number: "",
  start_date: "",
  end_date: "",
  status: "active",
  monthly_fee: "",
  notes: "",
};

function formFromContract(contract: ServiceContract | null): ContractFormValues {
  if (!contract) return emptyForm;

  return {
    elevator_uuid: contract.elevator_id,
    contract_number: contract.contract_number ?? "",
    start_date: toDateInput(contract.start_date),
    end_date: toDateInput(contract.end_date),
    status: contract.status,
    monthly_fee: contract.monthly_fee == null ? "" : String(contract.monthly_fee),
    notes: contract.notes ?? "",
  };
}

function formToInput(values: ContractFormValues): ContractInput {
  return {
    elevator_uuid: values.elevator_uuid,
    contract_number: blankToNull(values.contract_number),
    start_date: values.start_date,
    end_date: values.end_date,
    status: values.status,
    monthly_fee: numericOrNull(values.monthly_fee),
    notes: blankToNull(values.notes),
  };
}

function elevatorLabel(elevator: Elevator): string {
  const name = elevator.name ?? elevator.serial_number;
  return `${elevator.building_name} · ${name}`;
}

function ContractFormDialog({
  open,
  contract,
  elevators,
  errors,
  formError,
  isSubmitting,
  onOpenChange,
  onSubmit,
}: {
  open: boolean;
  contract: ServiceContract | null;
  elevators: Elevator[];
  errors: Record<string, string[]>;
  formError: string | null;
  isSubmitting: boolean;
  onOpenChange: (open: boolean) => void;
  onSubmit: (values: ContractFormValues) => Promise<void>;
}) {
  const [values, setValues] = React.useState<ContractFormValues>(() => formFromContract(contract));
  const isEditing = !!contract;

  React.useEffect(() => {
    if (open) setValues(formFromContract(contract));
  }, [contract, open]);

  const setValue = (field: keyof ContractFormValues, value: string) => {
    setValues((prev) => ({ ...prev, [field]: value }));
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent
        className="max-h-[90vh] overflow-y-auto sm:max-w-2xl"
        onInteractOutside={(event) => event.preventDefault()}
      >
        <DialogHeader>
          <DialogTitle>{isEditing ? "Sözleşmeyi Düzenle" : "Yeni Sözleşme"}</DialogTitle>
          <DialogDescription>
            Bakım sözleşmesinin asansörünü, dönemini ve ücret bilgilerini gir.
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
              >
                <SelectTrigger>
                  <SelectValue placeholder="Asansör seç" />
                </SelectTrigger>
                <SelectContent>
                  {elevators.map((elevator) => (
                    <SelectItem key={elevator.id} value={elevator.id}>
                      {elevatorLabel(elevator)}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </Field>
            <Field label="Sözleşme No" error={fieldError(errors, "contract_number")}>
              <Input
                value={values.contract_number}
                onChange={(event) => setValue("contract_number", event.target.value)}
                placeholder="Boş bırakılabilir"
              />
            </Field>
          </div>

          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="Başlangıç Tarihi" error={fieldError(errors, "start_date")}>
              <Input
                type="date"
                value={values.start_date}
                onChange={(event) => setValue("start_date", event.target.value)}
                required
              />
            </Field>
            <Field label="Bitiş Tarihi" error={fieldError(errors, "end_date")}>
              <Input
                type="date"
                value={values.end_date}
                onChange={(event) => setValue("end_date", event.target.value)}
                required
              />
            </Field>
          </div>

          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="Aylık Ücret (₺)" error={fieldError(errors, "monthly_fee")}>
              <Input
                type="number"
                min={0}
                step="0.01"
                value={values.monthly_fee}
                onChange={(event) => setValue("monthly_fee", event.target.value)}
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
              {isEditing ? "Kaydet" : "Sözleşme Ekle"}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

export function ContractsPage() {
  const [query, setQuery] = React.useState("");
  const [status, setStatus] = React.useState(ALL_VALUE);
  const [page, setPage] = React.useState(1);
  const form = useFormDialog<ServiceContract>();
  const del = useConfirmDelete<ServiceContract>();
  const debouncedQuery = useDebounced(query);

  React.useEffect(() => {
    setPage(1);
  }, [debouncedQuery, status]);

  const listParams = React.useMemo(
    () => ({
      page,
      perPage: 25,
      search: debouncedQuery,
      sort: "end_date",
      filter: { ...(status === ALL_VALUE ? {} : { status }) },
    }),
    [page, debouncedQuery, status]
  );
  const elevatorParams = React.useMemo(() => ({ perPage: 100, sort: "name" }), []);
  const { items: contracts, pagination, isLoading, error, reload } = useList(
    fetchContracts,
    listParams
  );
  const { items: elevatorOptions } = useList(fetchElevators, elevatorParams);

  const handleSubmit = (values: ContractFormValues) =>
    form.submit(async () => {
      const input = formToInput(values);
      if (form.editing) {
        await updateContract(form.editing.id, input);
      } else {
        await createContract(input);
      }
      reload();
    });

  return (
    <div className="space-y-5">
      <PageHeader
        title="Sözleşmeler"
        description="Asansör bakım sözleşmeleri ve yenileme takibi"
        count={pagination?.total ?? contracts.length}
        actions={
          <Button onClick={form.openCreate}>
            <Plus />
            Yeni Sözleşme
          </Button>
        }
      />

      <Toolbar>
        <SearchInput
          value={query}
          onChange={setQuery}
          placeholder="Sözleşme no veya bina ara..."
        />
        <FilterSelect
          value={status}
          onChange={setStatus}
          allLabel="Tüm Durumlar"
          options={statusOptions}
        />
      </Toolbar>

      {error && <ListError message={error.message} onRetry={reload} />}

      <DataTable
        columns={columns}
        data={contracts}
        getRowId={(c) => c.id}
        isLoading={isLoading}
        rowActions={(contract) => (
          <RowActionsMenu
            ariaLabel="Sözleşme işlemleri"
            onEdit={() => form.openEdit(contract)}
            onDelete={() => del.request(contract)}
          />
        )}
        empty={
          <EmptyState
            icon={FileText}
            title="Sözleşme bulunamadı"
            description="Arama veya filtre kriterlerine uyan sözleşme yok."
          />
        }
      />

      <Pagination pagination={pagination} onPageChange={setPage} />

      <ContractFormDialog
        open={form.open}
        contract={form.editing}
        elevators={elevatorOptions}
        errors={form.errors}
        formError={form.formError}
        isSubmitting={form.isSubmitting}
        onOpenChange={form.onOpenChange}
        onSubmit={handleSubmit}
      />

      <ConfirmDeleteDialog
        open={!!del.target}
        title="Sözleşmeyi Sil"
        description={`${
          del.target ? del.target.contract_number ?? "Numarasız" : ""
        } sözleşme kaydı silinecek. Bu işlem kaydı liste görünümünden kaldırır.`}
        error={del.error}
        isDeleting={del.isDeleting}
        onClose={del.close}
        onConfirm={() =>
          void del.confirm(async () => {
            if (!del.target) return;
            await deleteContract(del.target.id);
            reload();
          })
        }
      />
    </div>
  );
}
