import * as React from "react";
import { Building2, Loader2, MapPin, Plus } from "lucide-react";
import { PageHeader } from "@/components/common/PageHeader";
import { Toolbar } from "@/components/common/Toolbar";
import { SearchInput } from "@/components/common/SearchInput";
import { ALL_VALUE, FilterSelect } from "@/components/common/FilterSelect";
import { DataTable, type Column } from "@/components/common/DataTable";
import { EmptyState } from "@/components/common/EmptyState";
import { ListError } from "@/components/common/ListError";
import { LocationPicker } from "@/components/common/LocationPicker";
import { Pagination } from "@/components/common/Pagination";
import { StatusBadge } from "@/components/common/StatusBadge";
import { Field, FormErrorBanner } from "@/components/common/Field";
import { ConfirmDeleteDialog, useConfirmDelete } from "@/components/common/ConfirmDeleteDialog";
import { RowActionsMenu } from "@/components/common/RowActionsMenu";
import { Button } from "@/components/ui/button";
import {
  createBuilding,
  deleteBuilding,
  fetchBuildings,
  updateBuilding,
  type BuildingInput,
} from "@/api/resources";
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
import { useDebounced, useList } from "@/hooks/useList";
import { useFormDialog } from "@/hooks/useFormDialog";
import { blankToNull, fieldError, numericOrNull } from "@/lib/forms";
import type { Building } from "@/types";

const activeMeta = { label: "Aktif", variant: "success", dot: "bg-success" } as const;
const passiveMeta = { label: "Pasif", variant: "secondary", dot: "bg-muted-foreground" } as const;

const columns: Column<Building>[] = [
  {
    key: "name",
    header: "Bina",
    sortAccessor: (b) => b.name,
    cell: (b) => (
      <div className="min-w-0">
        <div className="font-medium text-foreground">{b.name}</div>
        {b.code && <div className="font-mono text-xs text-muted-foreground">{b.code}</div>}
      </div>
    ),
  },
  {
    key: "location",
    header: "Konum",
    sortAccessor: (b) => `${b.city} ${b.district}`,
    cell: (b) => (
      <div className="min-w-0 max-w-xs">
        <div className="flex items-center gap-1.5 text-foreground">
          <MapPin className="size-3.5 shrink-0 text-muted-foreground" />
          <span className="truncate" title={b.address}>
            {b.address}
          </span>
        </div>
        <div className="pl-5 text-xs text-muted-foreground">
          {b.district} / {b.city}
        </div>
      </div>
    ),
  },
  {
    key: "manager",
    header: "Bina Yöneticisi",
    hideOnMobile: true,
    cell: (b) =>
      b.manager_name ? (
        <div className="min-w-0">
          <div className="text-foreground">{b.manager_name}</div>
          <div className="text-xs text-muted-foreground">{b.manager_phone ?? "—"}</div>
        </div>
      ) : (
        <span className="text-muted-foreground">—</span>
      ),
  },
  {
    key: "elevators",
    header: "Asansör",
    align: "right",
    sortAccessor: (b) => b.elevator_count,
    cell: (b) => <span className="tabular-nums">{b.elevator_count}</span>,
  },
  {
    key: "status",
    header: "Durum",
    sortAccessor: (b) => (b.is_active ? 0 : 1),
    cell: (b) => <StatusBadge meta={b.is_active ? activeMeta : passiveMeta} />,
  },
];

interface BuildingFormValues {
  name: string;
  code: string;
  address: string;
  city: string;
  district: string;
  manager_name: string;
  manager_phone: string;
  entrance_code: string;
  access_notes: string;
  latitude: string;
  longitude: string;
  is_active: "true" | "false";
  notes: string;
}

const emptyForm: BuildingFormValues = {
  name: "",
  code: "",
  address: "",
  city: "",
  district: "",
  manager_name: "",
  manager_phone: "",
  entrance_code: "",
  access_notes: "",
  latitude: "",
  longitude: "",
  is_active: "true",
  notes: "",
};

function formFromBuilding(building: Building | null): BuildingFormValues {
  if (!building) return emptyForm;

  return {
    name: building.name,
    code: building.code ?? "",
    address: building.address,
    city: building.city,
    district: building.district,
    manager_name: building.manager_name ?? "",
    manager_phone: building.manager_phone ?? "",
    entrance_code: building.entrance_code ?? "",
    access_notes: building.access_notes ?? "",
    latitude: building.latitude == null ? "" : String(building.latitude),
    longitude: building.longitude == null ? "" : String(building.longitude),
    is_active: building.is_active ? "true" : "false",
    notes: building.notes ?? "",
  };
}

function formToInput(values: BuildingFormValues): BuildingInput {
  return {
    name: values.name.trim(),
    code: blankToNull(values.code),
    address: values.address.trim(),
    city: values.city.trim(),
    district: values.district.trim(),
    manager_name: blankToNull(values.manager_name),
    manager_phone: blankToNull(values.manager_phone),
    entrance_code: blankToNull(values.entrance_code),
    access_notes: blankToNull(values.access_notes),
    latitude: numericOrNull(values.latitude),
    longitude: numericOrNull(values.longitude),
    is_active: values.is_active === "true",
    notes: blankToNull(values.notes),
  };
}

function BuildingFormDialog({
  open,
  building,
  errors,
  formError,
  isSubmitting,
  onOpenChange,
  onSubmit,
}: {
  open: boolean;
  building: Building | null;
  errors: Record<string, string[]>;
  formError: string | null;
  isSubmitting: boolean;
  onOpenChange: (open: boolean) => void;
  onSubmit: (values: BuildingFormValues) => Promise<void>;
}) {
  const [values, setValues] = React.useState<BuildingFormValues>(() => formFromBuilding(building));
  const isEditing = !!building;

  React.useEffect(() => {
    if (open) setValues(formFromBuilding(building));
  }, [building, open]);

  const setValue = (field: keyof BuildingFormValues, value: string) => {
    setValues((prev) => ({ ...prev, [field]: value }));
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent
        className="max-h-[90vh] overflow-y-auto sm:max-w-2xl"
        onInteractOutside={(event) => event.preventDefault()}
      >
        <DialogHeader>
          <DialogTitle>{isEditing ? "Binayı Düzenle" : "Yeni Bina"}</DialogTitle>
          <DialogDescription>
            Hizmet verilen bina bilgilerini ve bina yöneticisi iletişim detaylarını gir.
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
            <Field label="Bina Adı" error={fieldError(errors, "name")}>
              <Input
                value={values.name}
                onChange={(event) => setValue("name", event.target.value)}
                required
              />
            </Field>
            <Field label="Kod" error={fieldError(errors, "code")}>
              <Input value={values.code} onChange={(event) => setValue("code", event.target.value)} />
            </Field>
          </div>

          <Field label="Adres" error={fieldError(errors, "address")}>
            <Textarea
              value={values.address}
              onChange={(event) => setValue("address", event.target.value)}
              required
            />
          </Field>

          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="Şehir" error={fieldError(errors, "city")}>
              <Input
                value={values.city}
                onChange={(event) => setValue("city", event.target.value)}
                required
              />
            </Field>
            <Field label="İlçe" error={fieldError(errors, "district")}>
              <Input
                value={values.district}
                onChange={(event) => setValue("district", event.target.value)}
                required
              />
            </Field>
          </div>

          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="Yönetici" error={fieldError(errors, "manager_name")}>
              <Input
                value={values.manager_name}
                onChange={(event) => setValue("manager_name", event.target.value)}
              />
            </Field>
            <Field label="Yönetici Telefonu" error={fieldError(errors, "manager_phone")}>
              <Input
                value={values.manager_phone}
                onChange={(event) => setValue("manager_phone", event.target.value)}
              />
            </Field>
          </div>

          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="Kapı Şifresi" error={fieldError(errors, "entrance_code")}>
              <Input
                value={values.entrance_code}
                onChange={(event) => setValue("entrance_code", event.target.value)}
              />
            </Field>
            <Field label="Giriş Notu" error={fieldError(errors, "access_notes")}>
              <Input
                value={values.access_notes}
                onChange={(event) => setValue("access_notes", event.target.value)}
                placeholder="Arka giriş, bodrum kat vb."
              />
            </Field>
          </div>

          <div className="space-y-1.5 text-sm">
            <span className="font-medium text-foreground">Konum</span>
            <LocationPicker
              latitude={values.latitude}
              longitude={values.longitude}
              searchQuery={[values.address, values.district, values.city]
                .map((part) => part.trim())
                .filter(Boolean)
                .join(", ")}
              onChange={(latitude, longitude) =>
                setValues((prev) => ({ ...prev, latitude, longitude }))
              }
            />
          </div>

          <div className="grid gap-4 sm:grid-cols-3">
            <Field label="Enlem" error={fieldError(errors, "latitude")}>
              <Input
                type="number"
                step="any"
                value={values.latitude}
                onChange={(event) => setValue("latitude", event.target.value)}
              />
            </Field>
            <Field label="Boylam" error={fieldError(errors, "longitude")}>
              <Input
                type="number"
                step="any"
                value={values.longitude}
                onChange={(event) => setValue("longitude", event.target.value)}
              />
            </Field>
            <Field label="Durum" error={fieldError(errors, "is_active")}>
              <Select
                value={values.is_active}
                onValueChange={(value) => setValue("is_active", value)}
              >
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="true">Aktif</SelectItem>
                  <SelectItem value="false">Pasif</SelectItem>
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
              {isEditing ? "Kaydet" : "Bina Ekle"}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

export function BuildingsPage() {
  const [query, setQuery] = React.useState("");
  const [district, setDistrict] = React.useState(ALL_VALUE);
  const [page, setPage] = React.useState(1);
  const form = useFormDialog<Building>();
  const del = useConfirmDelete<Building>();
  const debouncedQuery = useDebounced(query);

  React.useEffect(() => {
    setPage(1);
  }, [debouncedQuery, district]);

  const listParams = React.useMemo(
    () => ({
      page,
      perPage: 25,
      search: debouncedQuery,
      sort: "name",
      filter: { ...(district === ALL_VALUE ? {} : { district }) },
    }),
    [page, debouncedQuery, district]
  );
  const optionParams = React.useMemo(() => ({ perPage: 100, sort: "name" }), []);
  const { items: buildings, pagination, isLoading, error, reload } = useList(
    fetchBuildings,
    listParams
  );
  const { items: optionBuildings, reload: reloadDistrictOptions } = useList(
    fetchBuildings,
    optionParams
  );
  const districts = React.useMemo(
    () =>
      [...new Set(optionBuildings.map((b) => b.district))].sort((a, b) =>
        a.localeCompare(b, "tr-TR")
      ),
    [optionBuildings]
  );

  const handleSubmit = (values: BuildingFormValues) =>
    form.submit(async () => {
      const input = formToInput(values);
      if (form.editing) {
        await updateBuilding(form.editing.id, input);
      } else {
        await createBuilding(input);
      }
      reload();
      reloadDistrictOptions();
    });

  return (
    <div className="space-y-5">
      <PageHeader
        title="Binalar"
        description="Hizmet verilen binalar ve sorumluları"
        count={pagination?.total ?? buildings.length}
        actions={
          <Button onClick={form.openCreate}>
            <Plus />
            Yeni Bina
          </Button>
        }
      />

      <Toolbar>
        <SearchInput value={query} onChange={setQuery} placeholder="Bina, kod veya yönetici ara..." />
        <FilterSelect
          value={district}
          onChange={setDistrict}
          allLabel="Tüm İlçeler"
          options={districts.map((d) => ({ value: d, label: d }))}
        />
      </Toolbar>

      {error && <ListError message={error.message} onRetry={reload} />}

      <DataTable
        columns={columns}
        data={buildings}
        getRowId={(b) => b.id}
        isLoading={isLoading}
        rowActions={(building) => (
          <RowActionsMenu
            ariaLabel="Bina işlemleri"
            onEdit={() => form.openEdit(building)}
            onDelete={() => del.request(building)}
          />
        )}
        empty={
          <EmptyState
            icon={Building2}
            title="Bina bulunamadı"
            description="Arama veya filtre kriterlerine uyan bina yok."
          />
        }
      />

      <Pagination pagination={pagination} onPageChange={setPage} />

      <BuildingFormDialog
        open={form.open}
        building={form.editing}
        errors={form.errors}
        formError={form.formError}
        isSubmitting={form.isSubmitting}
        onOpenChange={form.onOpenChange}
        onSubmit={handleSubmit}
      />

      <ConfirmDeleteDialog
        open={!!del.target}
        title="Binayı Sil"
        description={`${del.target?.name ?? ""} kaydı silinecek. Bu işlem kaydı liste görünümünden kaldırır.`}
        error={del.error}
        isDeleting={del.isDeleting}
        onClose={del.close}
        onConfirm={() =>
          void del.confirm(async () => {
            if (!del.target) return;
            await deleteBuilding(del.target.id);
            reload();
            reloadDistrictOptions();
          })
        }
      />
    </div>
  );
}
