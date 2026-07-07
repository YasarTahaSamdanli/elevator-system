import * as React from "react";
import { Building2, Loader2, MapPin, MoreHorizontal, Pencil, Plus, Trash2 } from "lucide-react";
import { PageHeader } from "@/components/common/PageHeader";
import { Toolbar } from "@/components/common/Toolbar";
import { SearchInput } from "@/components/common/SearchInput";
import { ALL_VALUE, FilterSelect } from "@/components/common/FilterSelect";
import { DataTable, type Column } from "@/components/common/DataTable";
import { EmptyState } from "@/components/common/EmptyState";
import { ListError } from "@/components/common/ListError";
import { Pagination } from "@/components/common/Pagination";
import { StatusBadge } from "@/components/common/StatusBadge";
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
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { useDebounced, useList } from "@/hooks/useList";
import { ApiError } from "@/lib/api";
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
      <div className="flex items-center gap-1.5 text-muted-foreground">
        <MapPin className="size-3.5 shrink-0" />
        {b.district} / {b.city}
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
  latitude: "",
  longitude: "",
  is_active: "true",
  notes: "",
};

const blankToNull = (value: string): string | null => {
  const trimmed = value.trim();
  return trimmed === "" ? null : trimmed;
};

const numericOrNull = (value: string): number | null => {
  const trimmed = value.trim();
  return trimmed === "" ? null : Number(trimmed);
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
    latitude: numericOrNull(values.latitude),
    longitude: numericOrNull(values.longitude),
    is_active: values.is_active === "true",
    notes: blankToNull(values.notes),
  };
}

function fieldError(errors: Record<string, string[]>, field: keyof BuildingFormValues) {
  return errors[field]?.[0] ?? null;
}

function Field({
  label,
  error,
  children,
}: {
  label: string;
  error?: string | null;
  children: React.ReactNode;
}) {
  return (
    <label className="space-y-1.5 text-sm">
      <span className="font-medium text-foreground">{label}</span>
      {children}
      {error && <span className="block text-xs text-danger-foreground">{error}</span>}
    </label>
  );
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
      <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
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
            console.log("building form submit");
            void onSubmit(values);
          }}
        >
          {formError && (
            <div className="rounded-md bg-danger-subtle px-3 py-2 text-sm text-danger-foreground">
              {formError}
            </div>
          )}

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
            <textarea
              className="min-h-20 w-full rounded-md border border-input bg-surface px-3 py-2 text-sm shadow-xs transition-colors placeholder:text-muted-foreground/70 focus-visible:border-ring focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
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
            <textarea
              className="min-h-20 w-full rounded-md border border-input bg-surface px-3 py-2 text-sm shadow-xs transition-colors placeholder:text-muted-foreground/70 focus-visible:border-ring focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
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
  const [city, setCity] = React.useState(ALL_VALUE);
  const [page, setPage] = React.useState(1);
  const [formOpen, setFormOpen] = React.useState(false);
  const [editingBuilding, setEditingBuilding] = React.useState<Building | null>(null);
  const [deletingBuilding, setDeletingBuilding] = React.useState<Building | null>(null);
  const [formErrors, setFormErrors] = React.useState<Record<string, string[]>>({});
  const [formError, setFormError] = React.useState<string | null>(null);
  const [isSubmitting, setSubmitting] = React.useState(false);
  const [isDeleting, setDeleting] = React.useState(false);
  const debouncedQuery = useDebounced(query);

  React.useEffect(() => {
    setPage(1);
  }, [debouncedQuery, city]);

  const listFilter = React.useMemo<Record<string, string>>(() => {
    const filter: Record<string, string> = {};
    if (city !== ALL_VALUE) filter.city = city;
    return filter;
  }, [city]);

  const listParams = React.useMemo(
    () => ({
      page,
      perPage: 25,
      search: debouncedQuery,
      sort: "name",
      filter: listFilter,
    }),
    [page, debouncedQuery, listFilter]
  );
  const optionParams = React.useMemo(() => ({ perPage: 100, sort: "name" }), []);
  const { items: buildings, pagination, isLoading, error, reload } = useList(
    fetchBuildings,
    listParams
  );
  const { items: cityBuildings, reload: reloadCityOptions } = useList(fetchBuildings, optionParams);
  const cities = React.useMemo(
    () =>
      [...new Set(cityBuildings.map((b) => b.city))].sort((a, b) =>
        a.localeCompare(b, "tr-TR")
      ),
    [cityBuildings]
  );

  const openCreate = () => {
    setEditingBuilding(null);
    setFormErrors({});
    setFormError(null);
    setFormOpen(true);
  };

  const openEdit = (building: Building) => {
    setEditingBuilding(building);
    setFormErrors({});
    setFormError(null);
    setFormOpen(true);
  };

  const handleSubmit = async (values: BuildingFormValues) => {
    setSubmitting(true);
    setFormErrors({});
    setFormError(null);

    try {
      const input = formToInput(values);
      console.log("building submit payload", input);
      if (editingBuilding) {
        await updateBuilding(editingBuilding.id, input);
      } else {
        await createBuilding(input);
      }
      console.log("building submit success");
      setFormOpen(false);
      setEditingBuilding(null);
      reload();
      reloadCityOptions();
    } catch (err) {
      if (err instanceof ApiError) {
        setFormErrors(err.details);
        setFormError(err.message);
      } else {
        setFormError("Beklenmeyen bir hata oluştu.");
      }
    } finally {
      setSubmitting(false);
    }
  };

  const handleDelete = async () => {
    if (!deletingBuilding) return;
    setDeleting(true);

    try {
      await deleteBuilding(deletingBuilding.id);
      setDeletingBuilding(null);
      reload();
      reloadCityOptions();
    } finally {
      setDeleting(false);
    }
  };

  return (
    <div className="space-y-5">
      <PageHeader
        title="Binalar"
        description="Hizmet verilen binalar ve sorumluları"
        count={pagination?.total ?? buildings.length}
        actions={
          <Button onClick={openCreate}>
            <Plus />
            Yeni Bina
          </Button>
        }
      />

      <Toolbar>
        <SearchInput value={query} onChange={setQuery} placeholder="Bina, kod veya yönetici ara..." />
        <FilterSelect
          value={city}
          onChange={setCity}
          allLabel="Tüm Şehirler"
          options={cities.map((c) => ({ value: c, label: c }))}
        />
      </Toolbar>

      {error && <ListError message={error.message} onRetry={reload} />}

      <DataTable
        columns={columns}
        data={buildings}
        getRowId={(b) => b.id}
        isLoading={isLoading}
        rowActions={(building) => (
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="ghost" size="icon-sm" aria-label="Bina işlemleri">
                <MoreHorizontal />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuItem onSelect={() => openEdit(building)}>
                <Pencil className="size-4" />
                Düzenle
              </DropdownMenuItem>
              <DropdownMenuItem
                className="text-danger focus:text-danger"
                onSelect={() => setDeletingBuilding(building)}
              >
                <Trash2 className="size-4" />
                Sil
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
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
        open={formOpen}
        building={editingBuilding}
        errors={formErrors}
        formError={formError}
        isSubmitting={isSubmitting}
        onOpenChange={(open) => {
          setFormOpen(open);
          if (!open) setEditingBuilding(null);
        }}
        onSubmit={handleSubmit}
      />

      <Dialog open={!!deletingBuilding} onOpenChange={(open) => !open && setDeletingBuilding(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Binayı Sil</DialogTitle>
            <DialogDescription>
              {deletingBuilding?.name} kaydı silinecek. Bu işlem liste görünümünden kaldırır.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button variant="outline" disabled={isDeleting} onClick={() => setDeletingBuilding(null)}>
              Vazgeç
            </Button>
            <Button variant="destructive" disabled={isDeleting} onClick={handleDelete}>
              {isDeleting && <Loader2 className="animate-spin" />}
              Sil
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
