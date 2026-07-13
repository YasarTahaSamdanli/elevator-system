import * as React from "react";
import {
  ArrowLeftRight,
  Boxes,
  ClipboardList,
  Loader2,
  PackagePlus,
  Pencil,
  Plus,
  Warehouse as WarehouseIcon,
} from "lucide-react";
import { DataTable, type Column } from "@/components/common/DataTable";
import { EmptyState } from "@/components/common/EmptyState";
import { ListError } from "@/components/common/ListError";
import { PageHeader } from "@/components/common/PageHeader";
import { Pagination } from "@/components/common/Pagination";
import { SearchInput } from "@/components/common/SearchInput";
import { Toolbar } from "@/components/common/Toolbar";
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import {
  createMaterial,
  createStockMovement,
  createStockTransfer,
  createWarehouse,
  deleteMaterial,
  fetchMaterials,
  fetchStockMovements,
  fetchUsers,
  fetchWarehouses,
  updateMaterial,
  updateWarehouse,
  type MaterialInput,
  type StockMovementInput,
  type WarehouseInput,
} from "@/api/resources";
import { ApiError } from "@/lib/api";
import { formatCurrency, formatDateTime, formatNumber } from "@/lib/format";
import { blankToNull, fieldError } from "@/lib/forms";
import { cn } from "@/lib/utils";
import { useDebounced, useList } from "@/hooks/useList";
import type {
  Material,
  MaterialUnit,
  StockMovement,
  StockMovementType,
  User,
  Warehouse,
} from "@/types";

const unitLabels: Record<MaterialUnit, string> = {
  piece: "Adet",
  meter: "Metre",
  kg: "Kg",
  liter: "Litre",
  set: "Takım",
};

const movementLabels: Record<StockMovementType, string> = {
  purchase_in: "Mal Kabul",
  work_order_out: "İş Emri Çıkış",
  work_order_return: "İş Emri İade",
  transfer_in: "Transfer Giriş",
  transfer_out: "Transfer Çıkış",
  adjustment_in: "Sayım Düzeltme (+)",
  adjustment_out: "Sayım Düzeltme (−)",
};

const materialColumns: Column<Material>[] = [
  {
    key: "code",
    header: "Kod",
    sortAccessor: (m) => m.code,
    cell: (m) => <span className="font-mono text-xs text-foreground">{m.code}</span>,
  },
  {
    key: "name",
    header: "Malzeme",
    sortAccessor: (m) => m.name,
    cell: (m) => (
      <div className="min-w-0">
        <div className="truncate text-foreground">{m.name}</div>
        <div className="truncate text-xs text-muted-foreground">{m.category ?? "Kategorisiz"}</div>
      </div>
    ),
  },
  {
    key: "stock",
    header: "Stok",
    sortAccessor: (m) => m.stock_on_hand,
    cell: (m) => (
      <span
        className={cn(
          "tabular-nums",
          m.stock_on_hand < m.min_stock_level && "font-medium text-danger-foreground"
        )}
      >
        {formatNumber(m.stock_on_hand)} {unitLabels[m.unit]}
      </span>
    ),
  },
  {
    key: "min",
    header: "Min.",
    hideOnMobile: true,
    sortAccessor: (m) => m.min_stock_level,
    cell: (m) => <span className="tabular-nums text-muted-foreground">{formatNumber(m.min_stock_level)}</span>,
  },
  {
    key: "price",
    header: "Varsayılan Fiyat",
    hideOnMobile: true,
    sortAccessor: (m) => m.default_unit_price,
    cell: (m) => <span className="tabular-nums text-muted-foreground">{formatCurrency(m.default_unit_price)}</span>,
  },
];

const warehouseColumns: Column<Warehouse>[] = [
  {
    key: "name",
    header: "Depo",
    sortAccessor: (w) => w.name,
    cell: (w) => (
      <div>
        <div className="text-foreground">{w.name}</div>
        <div className="text-xs text-muted-foreground">{w.type === "main" ? "Merkez" : "Araç"}</div>
      </div>
    ),
  },
  {
    key: "user",
    header: "Zimmet",
    sortAccessor: (w) => w.user?.name ?? null,
    cell: (w) => <span className="text-muted-foreground">{w.user?.name ?? "—"}</span>,
  },
  {
    key: "status",
    header: "Durum",
    cell: (w) => <span className={w.is_active ? "text-success" : "text-muted-foreground"}>{w.is_active ? "Aktif" : "Pasif"}</span>,
  },
];

const movementColumns: Column<StockMovement>[] = [
  {
    key: "date",
    header: "Tarih",
    sortAccessor: (m) => m.occurred_at,
    cell: (m) => <span className="tabular-nums text-muted-foreground">{formatDateTime(m.occurred_at)}</span>,
  },
  {
    key: "material",
    header: "Malzeme",
    sortAccessor: (m) => `${m.material.code} ${m.material.name}`,
    cell: (m) => (
      <div>
        <div className="text-foreground">{m.material.name}</div>
        <div className="font-mono text-xs text-muted-foreground">{m.material.code}</div>
      </div>
    ),
  },
  {
    key: "type",
    header: "Hareket",
    sortAccessor: (m) => m.type,
    cell: (m) => <span>{movementLabels[m.type]}</span>,
  },
  {
    key: "quantity",
    header: "Miktar",
    sortAccessor: (m) => m.signed_quantity,
    cell: (m) => (
      <span className={cn("tabular-nums", m.signed_quantity < 0 ? "text-danger-foreground" : "text-success")}>
        {m.signed_quantity > 0 ? "+" : ""}
        {formatNumber(m.signed_quantity)} {unitLabels[m.material.unit]}
      </span>
    ),
  },
  {
    key: "warehouse",
    header: "Depo",
    hideOnMobile: true,
    sortAccessor: (m) => m.warehouse.name,
    cell: (m) => <span className="text-muted-foreground">{m.warehouse.name}</span>,
  },
];

function MaterialDialog({
  open,
  material,
  errors,
  formError,
  isSubmitting,
  onOpenChange,
  onSubmit,
}: {
  open: boolean;
  material: Material | null;
  errors: Record<string, string[]>;
  formError: string | null;
  isSubmitting: boolean;
  onOpenChange: (open: boolean) => void;
  onSubmit: (input: MaterialInput) => Promise<void>;
}) {
  const [values, setValues] = React.useState<MaterialInput>({
    code: "",
    name: "",
    unit: "piece",
    category: null,
    min_stock_level: 0,
    default_unit_price: null,
    default_sale_price: null,
    is_active: true,
    notes: null,
  });

  React.useEffect(() => {
    if (!open) return;
    setValues(
      material
        ? {
            code: material.code,
            name: material.name,
            unit: material.unit,
            category: material.category,
            min_stock_level: material.min_stock_level,
            default_unit_price: material.default_unit_price,
            default_sale_price: material.default_sale_price,
            is_active: material.is_active,
            notes: material.notes,
          }
        : {
            code: "",
            name: "",
            unit: "piece",
            category: null,
            min_stock_level: 0,
            default_unit_price: null,
            default_sale_price: null,
            is_active: true,
            notes: null,
          }
    );
  }, [open, material]);

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{material ? "Malzemeyi Düzenle" : "Yeni Malzeme"}</DialogTitle>
          <DialogDescription>Kod, birim, minimum stok ve varsayılan fiyat bilgilerini gir.</DialogDescription>
        </DialogHeader>
        <form
          className="space-y-4"
          onSubmit={(event) => {
            event.preventDefault();
            void onSubmit(values);
          }}
        >
          <FormErrorBanner message={formError} />
          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="Kod" error={fieldError(errors, "code")}>
              <Input value={values.code} onChange={(e) => setValues((p) => ({ ...p, code: e.target.value }))} />
            </Field>
            <Field label="Birim" error={fieldError(errors, "unit")}>
              <Select value={values.unit} onValueChange={(unit: MaterialUnit) => setValues((p) => ({ ...p, unit }))}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  {Object.entries(unitLabels).map(([value, label]) => <SelectItem key={value} value={value}>{label}</SelectItem>)}
                </SelectContent>
              </Select>
            </Field>
          </div>
          <Field label="Ad" error={fieldError(errors, "name")}>
            <Input value={values.name} onChange={(e) => setValues((p) => ({ ...p, name: e.target.value }))} />
          </Field>
          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="Kategori" error={fieldError(errors, "category")}>
              <Input value={values.category ?? ""} onChange={(e) => setValues((p) => ({ ...p, category: blankToNull(e.target.value) }))} />
            </Field>
            <Field label="Minimum Stok" error={fieldError(errors, "min_stock_level")}>
              <Input type="number" min="0" step="0.001" value={values.min_stock_level} onChange={(e) => setValues((p) => ({ ...p, min_stock_level: Number(e.target.value) }))} />
            </Field>
            <Field label="Alış Fiyatı (maliyet)" error={fieldError(errors, "default_unit_price")}>
              <Input type="number" min="0" step="0.01" value={values.default_unit_price ?? ""} onChange={(e) => setValues((p) => ({ ...p, default_unit_price: e.target.value === "" ? null : Number(e.target.value) }))} />
            </Field>
            <Field label="Satış Fiyatı (müşteriye)" error={fieldError(errors, "default_sale_price")}>
              <Input type="number" min="0" step="0.01" value={values.default_sale_price ?? ""} onChange={(e) => setValues((p) => ({ ...p, default_sale_price: e.target.value === "" ? null : Number(e.target.value) }))} />
            </Field>
          </div>
          <Field label="Notlar" error={fieldError(errors, "notes")}>
            <Textarea value={values.notes ?? ""} onChange={(e) => setValues((p) => ({ ...p, notes: blankToNull(e.target.value) }))} />
          </Field>
          <DialogFooter>
            <Button type="button" variant="outline" disabled={isSubmitting} onClick={() => onOpenChange(false)}>Vazgeç</Button>
            <Button type="submit" disabled={isSubmitting}>{isSubmitting && <Loader2 className="animate-spin" />}Kaydet</Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

function WarehouseDialog({
  open,
  warehouse,
  users,
  errors,
  formError,
  isSubmitting,
  onOpenChange,
  onSubmit,
}: {
  open: boolean;
  warehouse: Warehouse | null;
  users: User[];
  errors: Record<string, string[]>;
  formError: string | null;
  isSubmitting: boolean;
  onOpenChange: (open: boolean) => void;
  onSubmit: (input: WarehouseInput) => Promise<void>;
}) {
  const [values, setValues] = React.useState<WarehouseInput>({ name: "", type: "main", user_uuid: null, is_active: true });

  React.useEffect(() => {
    if (!open) return;
    setValues(warehouse ? { name: warehouse.name, type: warehouse.type, user_uuid: warehouse.user?.id ?? null, is_active: warehouse.is_active } : { name: "", type: "main", user_uuid: null, is_active: true });
  }, [open, warehouse]);

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{warehouse ? "Depoyu Düzenle" : "Yeni Depo"}</DialogTitle>
          <DialogDescription>Faz 2 merkez depo ile başlar; araç depoları için şema hazır.</DialogDescription>
        </DialogHeader>
        <form className="space-y-4" onSubmit={(e) => { e.preventDefault(); void onSubmit(values); }}>
          <FormErrorBanner message={formError} />
          <Field label="Depo Adı" error={fieldError(errors, "name")}>
            <Input value={values.name} onChange={(e) => setValues((p) => ({ ...p, name: e.target.value }))} />
          </Field>
          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="Tip" error={fieldError(errors, "type")}>
              <Select value={values.type} onValueChange={(type: Warehouse["type"]) => setValues((p) => ({ ...p, type, user_uuid: type === "main" ? null : p.user_uuid }))}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="main">Merkez</SelectItem>
                  <SelectItem value="vehicle">Araç</SelectItem>
                </SelectContent>
              </Select>
            </Field>
            <Field label="Zimmet" error={fieldError(errors, "user_uuid")}>
              <Select value={values.user_uuid ?? "__none__"} disabled={values.type === "main"} onValueChange={(value) => setValues((p) => ({ ...p, user_uuid: value === "__none__" ? null : value }))}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="__none__">Yok</SelectItem>
                  {users.map((u) => <SelectItem key={u.id} value={u.id}>{u.name}</SelectItem>)}
                </SelectContent>
              </Select>
            </Field>
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" disabled={isSubmitting} onClick={() => onOpenChange(false)}>Vazgeç</Button>
            <Button type="submit" disabled={isSubmitting}>{isSubmitting && <Loader2 className="animate-spin" />}Kaydet</Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

function ReceiptDialog({
  open,
  materials,
  warehouses,
  errors,
  formError,
  isSubmitting,
  onOpenChange,
  onSubmit,
}: {
  open: boolean;
  materials: Material[];
  warehouses: Warehouse[];
  errors: Record<string, string[]>;
  formError: string | null;
  isSubmitting: boolean;
  onOpenChange: (open: boolean) => void;
  onSubmit: (input: StockMovementInput) => Promise<void>;
}) {
  const mainWarehouse = warehouses.find((w) => w.type === "main") ?? warehouses[0];
  const [values, setValues] = React.useState<StockMovementInput>({
    material_uuid: "",
    warehouse_uuid: "",
    type: "purchase_in",
    quantity: 1,
    unit_price: null,
    occurred_at: null,
    note: null,
    update_material_price: true,
  });

  React.useEffect(() => {
    if (open) {
      setValues({
        material_uuid: materials[0]?.id ?? "",
        warehouse_uuid: mainWarehouse?.id ?? "",
        type: "purchase_in",
        quantity: 1,
        unit_price: null,
        occurred_at: null,
        note: null,
        update_material_price: true,
      });
    }
  }, [open, materials, mainWarehouse?.id]);

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Mal Kabul</DialogTitle>
          <DialogDescription>Giriş hareketi deftere eklenir; silme yerine ters kayıt mantığı korunur.</DialogDescription>
        </DialogHeader>
        <form className="space-y-4" onSubmit={(e) => { e.preventDefault(); void onSubmit(values); }}>
          <FormErrorBanner message={formError} />
          <Field label="Malzeme" error={fieldError(errors, "material_uuid")}>
            <Select value={values.material_uuid || undefined} onValueChange={(material_uuid) => setValues((p) => ({ ...p, material_uuid }))}>
              <SelectTrigger><SelectValue placeholder="Malzeme seç" /></SelectTrigger>
              <SelectContent>
                {materials.map((m) => <SelectItem key={m.id} value={m.id}>{m.code} · {m.name}</SelectItem>)}
              </SelectContent>
            </Select>
          </Field>
          <Field label="Depo" error={fieldError(errors, "warehouse_uuid")}>
            <Select value={values.warehouse_uuid || undefined} onValueChange={(warehouse_uuid) => setValues((p) => ({ ...p, warehouse_uuid }))}>
              <SelectTrigger><SelectValue placeholder="Depo seç" /></SelectTrigger>
              <SelectContent>
                {warehouses.map((w) => <SelectItem key={w.id} value={w.id}>{w.name}</SelectItem>)}
              </SelectContent>
            </Select>
          </Field>
          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="Miktar" error={fieldError(errors, "quantity")}>
              <Input type="number" min="0.001" step="0.001" value={values.quantity} onChange={(e) => setValues((p) => ({ ...p, quantity: Number(e.target.value) }))} />
            </Field>
            <Field label="Birim Fiyat" error={fieldError(errors, "unit_price")}>
              <Input type="number" min="0" step="0.01" value={values.unit_price ?? ""} onChange={(e) => setValues((p) => ({ ...p, unit_price: e.target.value === "" ? null : Number(e.target.value) }))} />
            </Field>
          </div>
          <Field label="Not" error={fieldError(errors, "note")}>
            <Input value={values.note ?? ""} onChange={(e) => setValues((p) => ({ ...p, note: blankToNull(e.target.value) }))} />
          </Field>
          <DialogFooter>
            <Button type="button" variant="outline" disabled={isSubmitting} onClick={() => onOpenChange(false)}>Vazgeç</Button>
            <Button type="submit" disabled={isSubmitting}>{isSubmitting && <Loader2 className="animate-spin" />}Girişi Kaydet</Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

function TransferDialog({
  open,
  materials,
  warehouses,
  errors,
  formError,
  isSubmitting,
  onOpenChange,
  onSubmit,
}: {
  open: boolean;
  materials: Material[];
  warehouses: Warehouse[];
  errors: Record<string, string[]>;
  formError: string | null;
  isSubmitting: boolean;
  onOpenChange: (open: boolean) => void;
  onSubmit: (values: { material_uuid: string; from_warehouse_uuid: string; to_warehouse_uuid: string; quantity: number; note: string | null }) => Promise<void>;
}) {
  const mainWarehouse = warehouses.find((w) => w.type === "main") ?? warehouses[0];
  const vehicleWarehouse = warehouses.find((w) => w.type === "vehicle") ?? warehouses.find((w) => w.id !== mainWarehouse?.id);
  const [values, setValues] = React.useState({
    material_uuid: "",
    from_warehouse_uuid: "",
    to_warehouse_uuid: "",
    quantity: 1,
    note: null as string | null,
  });

  React.useEffect(() => {
    if (!open) return;
    setValues({
      material_uuid: materials[0]?.id ?? "",
      from_warehouse_uuid: mainWarehouse?.id ?? "",
      to_warehouse_uuid: vehicleWarehouse?.id ?? "",
      quantity: 1,
      note: null,
    });
  }, [open, materials, mainWarehouse?.id, vehicleWarehouse?.id]);

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Depo Transferi</DialogTitle>
          <DialogDescription>Çıkış ve giriş hareketleri aynı transfer numarasıyla deftere yazılır.</DialogDescription>
        </DialogHeader>
        <form className="space-y-4" onSubmit={(e) => { e.preventDefault(); void onSubmit(values); }}>
          <FormErrorBanner message={formError} />
          <Field label="Malzeme" error={fieldError(errors, "material_uuid")}>
            <Select value={values.material_uuid || undefined} onValueChange={(material_uuid) => setValues((p) => ({ ...p, material_uuid }))}>
              <SelectTrigger><SelectValue placeholder="Malzeme seç" /></SelectTrigger>
              <SelectContent>
                {materials.map((m) => <SelectItem key={m.id} value={m.id}>{m.code} · {m.name}</SelectItem>)}
              </SelectContent>
            </Select>
          </Field>
          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="Çıkış Deposu" error={fieldError(errors, "warehouse_uuid")}>
              <Select value={values.from_warehouse_uuid || undefined} onValueChange={(from_warehouse_uuid) => setValues((p) => ({ ...p, from_warehouse_uuid }))}>
                <SelectTrigger><SelectValue placeholder="Depo seç" /></SelectTrigger>
                <SelectContent>
                  {warehouses.map((w) => <SelectItem key={w.id} value={w.id}>{w.name}</SelectItem>)}
                </SelectContent>
              </Select>
            </Field>
            <Field label="Giriş Deposu" error={fieldError(errors, "warehouse_uuid")}>
              <Select value={values.to_warehouse_uuid || undefined} onValueChange={(to_warehouse_uuid) => setValues((p) => ({ ...p, to_warehouse_uuid }))}>
                <SelectTrigger><SelectValue placeholder="Depo seç" /></SelectTrigger>
                <SelectContent>
                  {warehouses.map((w) => <SelectItem key={w.id} value={w.id}>{w.name}</SelectItem>)}
                </SelectContent>
              </Select>
            </Field>
          </div>
          <Field label="Miktar" error={fieldError(errors, "quantity")}>
            <Input type="number" min="0.001" step="0.001" value={values.quantity} onChange={(e) => setValues((p) => ({ ...p, quantity: Number(e.target.value) }))} />
          </Field>
          <Field label="Not" error={fieldError(errors, "note")}>
            <Input value={values.note ?? ""} onChange={(e) => setValues((p) => ({ ...p, note: blankToNull(e.target.value) }))} />
          </Field>
          <DialogFooter>
            <Button type="button" variant="outline" disabled={isSubmitting} onClick={() => onOpenChange(false)}>Vazgeç</Button>
            <Button type="submit" disabled={isSubmitting || values.from_warehouse_uuid === values.to_warehouse_uuid}>
              {isSubmitting && <Loader2 className="animate-spin" />}
              Transfer Et
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

export function InventoryPage() {
  const [query, setQuery] = React.useState("");
  const [page, setPage] = React.useState(1);
  const [materialDialogOpen, setMaterialDialogOpen] = React.useState(false);
  const [warehouseDialogOpen, setWarehouseDialogOpen] = React.useState(false);
  const [receiptDialogOpen, setReceiptDialogOpen] = React.useState(false);
  const [transferDialogOpen, setTransferDialogOpen] = React.useState(false);
  const [editingMaterial, setEditingMaterial] = React.useState<Material | null>(null);
  const [editingWarehouse, setEditingWarehouse] = React.useState<Warehouse | null>(null);
  const del = useConfirmDelete<Material>();
  const [errors, setErrors] = React.useState<Record<string, string[]>>({});
  const [formError, setFormError] = React.useState<string | null>(null);
  const [isSubmitting, setSubmitting] = React.useState(false);
  const debouncedQuery = useDebounced(query);

  const materialParams = React.useMemo(() => ({ page, perPage: 25, search: debouncedQuery, sort: "code" }), [page, debouncedQuery]);
  const { items: materials, pagination, isLoading, error, reload: reloadMaterials } = useList(fetchMaterials, materialParams);
  const { items: warehouses, reload: reloadWarehouses } = useList(fetchWarehouses, React.useMemo(() => ({ perPage: 100, sort: "name" }), []));
  const { items: movements, pagination: movementPagination, reload: reloadMovements } = useList(fetchStockMovements, React.useMemo(() => ({ perPage: 25, sort: "-occurred_at" }), []));
  const { items: users } = useList(fetchUsers, React.useMemo(() => ({ perPage: 100, sort: "name" }), []));

  React.useEffect(() => setPage(1), [debouncedQuery]);

  const resetFormState = () => {
    setErrors({});
    setFormError(null);
  };

  const submit = async (work: () => Promise<void>) => {
    setSubmitting(true);
    resetFormState();
    try {
      await work();
    } catch (err) {
      if (err instanceof ApiError) {
        setErrors(err.details);
        setFormError(err.message);
      } else {
        setFormError("Beklenmeyen bir hata oluştu.");
      }
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="space-y-5">
      <PageHeader
        title="Envanter"
        description="Malzeme kataloğu, depolar ve stok hareket defteri"
        count={pagination?.total ?? materials.length}
        actions={
          <div className="flex gap-2">
            <Button variant="outline" onClick={() => { resetFormState(); setReceiptDialogOpen(true); }}>
              <PackagePlus /> Mal Kabul
            </Button>
            <Button variant="outline" onClick={() => { resetFormState(); setTransferDialogOpen(true); }}>
              <ArrowLeftRight /> Transfer
            </Button>
            <Button onClick={() => { resetFormState(); setEditingMaterial(null); setMaterialDialogOpen(true); }}>
              <Plus /> Malzeme
            </Button>
          </div>
        }
      />

      <Tabs defaultValue="materials" className="space-y-4">
        <TabsList>
          <TabsTrigger value="materials"><Boxes className="size-4" />Katalog</TabsTrigger>
          <TabsTrigger value="warehouses"><WarehouseIcon className="size-4" />Depolar</TabsTrigger>
          <TabsTrigger value="movements"><ClipboardList className="size-4" />Hareketler</TabsTrigger>
        </TabsList>

        <TabsContent value="materials" className="space-y-4">
          <Toolbar>
            <SearchInput value={query} onChange={setQuery} placeholder="Kod, malzeme veya kategori ara..." />
          </Toolbar>
          {error && <ListError message={error.message} onRetry={reloadMaterials} />}
          <DataTable
            columns={materialColumns}
            data={materials}
            getRowId={(m) => m.id}
            isLoading={isLoading}
            rowActions={(material) => (
              <RowActionsMenu
                ariaLabel="Malzeme işlemleri"
                onEdit={() => { resetFormState(); setEditingMaterial(material); setMaterialDialogOpen(true); }}
                onDelete={() => del.request(material)}
              />
            )}
            empty={<EmptyState icon={Boxes} title="Malzeme yok" description="Katalog için ilk malzemeyi ekle." />}
          />
          <Pagination pagination={pagination} onPageChange={setPage} />
        </TabsContent>

        <TabsContent value="warehouses" className="space-y-4">
          <div className="flex justify-end">
            <Button onClick={() => { resetFormState(); setEditingWarehouse(null); setWarehouseDialogOpen(true); }}><Plus />Depo</Button>
          </div>
          <DataTable
            columns={warehouseColumns}
            data={warehouses}
            getRowId={(w) => w.id}
            rowActions={(warehouse) => (
              <Button variant="ghost" size="icon-sm" aria-label="Depoyu düzenle" onClick={() => { resetFormState(); setEditingWarehouse(warehouse); setWarehouseDialogOpen(true); }}>
                <Pencil />
              </Button>
            )}
            empty={<EmptyState icon={WarehouseIcon} title="Depo yok" description="Merkez depoyu oluşturarak başla." />}
          />
        </TabsContent>

        <TabsContent value="movements" className="space-y-4">
          <DataTable
            columns={movementColumns}
            data={movements}
            getRowId={(m) => m.id}
            empty={<EmptyState icon={ClipboardList} title="Hareket yok" description="Mal kabul yaptığında defter dolmaya başlar." />}
          />
          <Pagination pagination={movementPagination} onPageChange={() => undefined} />
        </TabsContent>
      </Tabs>

      <MaterialDialog
        open={materialDialogOpen}
        material={editingMaterial}
        errors={errors}
        formError={formError}
        isSubmitting={isSubmitting}
        onOpenChange={(open) => { setMaterialDialogOpen(open); if (!open) setEditingMaterial(null); }}
        onSubmit={(input) => submit(async () => {
          if (editingMaterial) await updateMaterial(editingMaterial.id, input);
          else await createMaterial(input);
          setMaterialDialogOpen(false);
          setEditingMaterial(null);
          reloadMaterials();
        })}
      />
      <WarehouseDialog
        open={warehouseDialogOpen}
        warehouse={editingWarehouse}
        users={users}
        errors={errors}
        formError={formError}
        isSubmitting={isSubmitting}
        onOpenChange={(open) => { setWarehouseDialogOpen(open); if (!open) setEditingWarehouse(null); }}
        onSubmit={(input) => submit(async () => {
          if (editingWarehouse) await updateWarehouse(editingWarehouse.id, input);
          else await createWarehouse(input);
          setWarehouseDialogOpen(false);
          setEditingWarehouse(null);
          reloadWarehouses();
        })}
      />
      <ReceiptDialog
        open={receiptDialogOpen}
        materials={materials}
        warehouses={warehouses}
        errors={errors}
        formError={formError}
        isSubmitting={isSubmitting}
        onOpenChange={setReceiptDialogOpen}
        onSubmit={(input) => submit(async () => {
          await createStockMovement(input);
          setReceiptDialogOpen(false);
          reloadMaterials();
          reloadMovements();
        })}
      />
      <TransferDialog
        open={transferDialogOpen}
        materials={materials}
        warehouses={warehouses}
        errors={errors}
        formError={formError}
        isSubmitting={isSubmitting}
        onOpenChange={setTransferDialogOpen}
        onSubmit={(input) => submit(async () => {
          await createStockTransfer({
            material_uuid: input.material_uuid,
            from_warehouse_uuid: input.from_warehouse_uuid,
            to_warehouse_uuid: input.to_warehouse_uuid,
            quantity: input.quantity,
            note: input.note,
          });
          setTransferDialogOpen(false);
          reloadMaterials();
          reloadMovements();
        })}
      />

      <ConfirmDeleteDialog
        open={!!del.target}
        title="Malzemeyi Sil"
        description={del.target ? `${del.target.code} kodlu malzeme katalogdan kaldırılacak.` : ""}
        error={del.error}
        isDeleting={del.isDeleting}
        onClose={del.close}
        onConfirm={() =>
          void del.confirm(async () => {
            if (!del.target) return;
            await deleteMaterial(del.target.id);
            reloadMaterials();
          })
        }
      />
    </div>
  );
}
