import * as React from "react";
import {
  AlertTriangle,
  Ban,
  CalendarClock,
  CheckCircle2,
  Circle,
  ClipboardCheck,
  ClipboardList,
  Hammer,
  Loader2,
  MoreHorizontal,
  Pencil,
  Play,
  Plus,
  Sparkles,
  Trash2,
  Wrench,
  type LucideIcon,
} from "lucide-react";
import { PageHeader } from "@/components/common/PageHeader";
import { Toolbar } from "@/components/common/Toolbar";
import { SearchInput } from "@/components/common/SearchInput";
import { ALL_VALUE, FilterSelect } from "@/components/common/FilterSelect";
import { DataTable, type Column } from "@/components/common/DataTable";
import { EmptyState } from "@/components/common/EmptyState";
import { ListError } from "@/components/common/ListError";
import { Pagination } from "@/components/common/Pagination";
import { StatusBadge } from "@/components/common/StatusBadge";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import { Button } from "@/components/ui/button";
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
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet";
import {
  workOrderPriorityMeta,
  workOrderStatusMeta,
  workOrderTypeMeta,
} from "@/lib/status";
import { formatDateTime, initials } from "@/lib/format";
import { cn } from "@/lib/utils";
import {
  createWorkOrder,
  deleteWorkOrder,
  fetchContracts,
  fetchUsers,
  fetchWorkOrder,
  fetchWorkOrders,
  updateWorkOrder,
  updateWorkOrderChecklistItem,
  updateWorkOrderStatus,
  type WorkOrderInput,
} from "@/api/resources";
import { useDebounced, useList } from "@/hooks/useList";
import { ApiError } from "@/lib/api";
import type {
  ServiceContract,
  User,
  WorkOrder,
  WorkOrderChecklistItem,
  WorkOrderPriority,
  WorkOrderStatus,
  WorkOrderType,
} from "@/types";

const typeIcons: Record<WorkOrderType, LucideIcon> = {
  maintenance: Wrench,
  fault: AlertTriangle,
  inspection: ClipboardCheck,
  modernization: Sparkles,
  repair: Hammer,
};

const columns: Column<WorkOrder>[] = [
  {
    key: "number",
    header: "İş Emri",
    sortAccessor: (wo) => wo.work_order_number,
    cell: (wo) => {
      const TypeIcon = typeIcons[wo.type];
      return (
        <div className="min-w-0">
          <div className="font-mono text-xs text-foreground">{wo.work_order_number}</div>
          <div className="flex items-center gap-1 text-xs text-muted-foreground">
            <TypeIcon className="size-3" />
            {workOrderTypeMeta[wo.type].label}
          </div>
        </div>
      );
    },
  },
  {
    key: "location",
    header: "Bina / Asansör",
    sortAccessor: (wo) => `${wo.building_name} ${wo.elevator_name}`,
    cell: (wo) => (
      <div className="min-w-0">
        <div className="truncate text-foreground">{wo.building_name}</div>
        <div className="truncate text-xs text-muted-foreground">{wo.elevator_name}</div>
      </div>
    ),
  },
  {
    key: "priority",
    header: "Öncelik",
    hideOnMobile: true,
    sortAccessor: (wo) => wo.priority,
    cell: (wo) => <StatusBadge meta={workOrderPriorityMeta[wo.priority]} />,
  },
  {
    key: "status",
    header: "Durum",
    sortAccessor: (wo) => wo.status,
    cell: (wo) => <StatusBadge meta={workOrderStatusMeta[wo.status]} dot={false} />,
  },
  {
    key: "assignee",
    header: "Teknisyen",
    hideOnMobile: true,
    sortAccessor: (wo) => wo.assigned_user?.name ?? null,
    cell: (wo) =>
      wo.assigned_user ? (
        <div className="flex items-center gap-2">
          <Avatar className="size-6">
            <AvatarFallback className="bg-muted text-[10px] font-medium text-muted-foreground">
              {initials(wo.assigned_user.name)}
            </AvatarFallback>
          </Avatar>
          <span className="truncate">{wo.assigned_user.name}</span>
        </div>
      ) : (
        <span className="text-muted-foreground">Atanmadı</span>
      ),
  },
  {
    key: "scheduled",
    header: "Planlanan",
    hideOnMobile: true,
    sortAccessor: (wo) => wo.scheduled_at,
    cell: (wo) => (
      <span className="tabular-nums text-muted-foreground">{formatDateTime(wo.scheduled_at)}</span>
    ),
  },
];

function SectionLabel({ children }: { children: React.ReactNode }) {
  return (
    <h4 className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
      {children}
    </h4>
  );
}

function TimelineItem({
  icon: Icon,
  label,
  value,
  done,
  last,
}: {
  icon: LucideIcon;
  label: string;
  value: string | null;
  done: boolean;
  last?: boolean;
}) {
  return (
    <div className="flex gap-3.5">
      <div className="flex flex-col items-center">
        <div
          className={cn(
            "flex size-8 shrink-0 items-center justify-center rounded-full border",
            done
              ? "border-primary/25 bg-primary/10 text-primary"
              : "border-border bg-muted/40 text-muted-foreground"
          )}
        >
          <Icon className="size-3.5" />
        </div>
        {!last && <div className="my-1 w-px flex-1 bg-border" />}
      </div>
      <div className={cn("pt-1", !last && "pb-6")}>
        <div className="text-sm font-medium text-foreground">{label}</div>
        <div className="text-sm tabular-nums text-muted-foreground">
          {formatDateTime(value)}
        </div>
      </div>
    </div>
  );
}

/** The forward transition offered as the primary quick action per status. */
const nextTransition: Partial<Record<WorkOrderStatus, { status: WorkOrderStatus; label: string }>> = {
  draft: { status: "in_progress", label: "Başlat" },
  planned: { status: "in_progress", label: "Başlat" },
  assigned: { status: "in_progress", label: "Başlat" },
  in_progress: { status: "completed", label: "Tamamla" },
};

function WorkOrderSheet({
  workOrder,
  onClose,
  onEdit,
  onDelete,
  onChanged,
}: {
  workOrder: WorkOrder | null;
  onClose: () => void;
  onEdit: (workOrder: WorkOrder) => void;
  onDelete: (workOrder: WorkOrder) => void;
  onChanged: () => void;
}) {
  const [detail, setDetail] = React.useState<WorkOrder | null>(null);
  const [actionError, setActionError] = React.useState<string | null>(null);
  const [isTransitioning, setTransitioning] = React.useState(false);
  const [confirmingCancel, setConfirmingCancel] = React.useState(false);

  const workOrderId = workOrder?.id;

  React.useEffect(() => {
    setDetail(null);
    setActionError(null);
    setConfirmingCancel(false);
    if (!workOrderId) return;

    let stale = false;
    fetchWorkOrder(workOrderId)
      .then((loaded) => {
        if (!stale) setDetail(loaded);
      })
      .catch(() => {
        if (!stale) setActionError("İş emri detayı yüklenemedi.");
      });

    return () => {
      stale = true;
    };
  }, [workOrderId]);

  // The list row opens the sheet instantly; the detail response replaces it
  // once loaded (same fields plus the checklist).
  const shown = detail ?? workOrder;
  const checklist = detail?.checklist ?? [];
  const doneCount = checklist.filter((item) => item.is_done).length;

  const toggleItem = async (item: WorkOrderChecklistItem) => {
    if (!detail) return;
    const nextDone = !item.is_done;

    const apply = (done: boolean) =>
      setDetail((prev) =>
        prev
          ? {
              ...prev,
              checklist: prev.checklist?.map((row) =>
                row.id === item.id ? { ...row, is_done: done } : row
              ),
            }
          : prev
      );

    apply(nextDone);
    try {
      await updateWorkOrderChecklistItem(detail.id, item.id, { is_done: nextDone });
    } catch {
      apply(!nextDone);
      setActionError("Kontrol maddesi güncellenemedi.");
    }
  };

  const transition = async (status: WorkOrderStatus) => {
    if (!workOrderId) return;
    setTransitioning(true);
    setActionError(null);

    try {
      const updated = await updateWorkOrderStatus(workOrderId, status);
      setDetail(updated);
      setConfirmingCancel(false);
      onChanged();
    } catch (err) {
      setActionError(err instanceof ApiError ? err.message : "Durum güncellenemedi.");
    } finally {
      setTransitioning(false);
    }
  };

  const quickAction = shown ? nextTransition[shown.status] : undefined;
  const cancellable = shown ? shown.status !== "completed" && shown.status !== "cancelled" : false;

  const TypeIcon = shown ? typeIcons[shown.type] : null;

  return (
    <Sheet open={!!workOrder} onOpenChange={(open) => !open && onClose()}>
      <SheetContent
        className="flex w-full flex-col gap-0 p-0 sm:max-w-lg"
        onOpenAutoFocus={(e) => e.preventDefault()}
      >
        {shown && TypeIcon && (
          <>
            <SheetHeader className="space-y-2 border-b border-border px-6 py-5 pr-14">
              <div className="flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                <TypeIcon className="size-3.5" />
                {workOrderTypeMeta[shown.type].label}
              </div>
              <SheetTitle className="font-mono text-lg">
                {shown.work_order_number}
              </SheetTitle>
              <SheetDescription>
                {shown.building_name} · {shown.elevator_name}
              </SheetDescription>
              <div className="flex flex-wrap gap-2 pt-1.5">
                <StatusBadge meta={workOrderStatusMeta[shown.status]} dot={false} />
                <StatusBadge meta={workOrderPriorityMeta[shown.priority]} />
              </div>
            </SheetHeader>

            <div className="flex-1 space-y-7 overflow-y-auto px-6 py-6">
              {shown.description && (
                <section className="space-y-2.5">
                  <SectionLabel>Açıklama</SectionLabel>
                  <p className="rounded-lg border border-border bg-muted/40 p-4 text-sm leading-6 text-foreground">
                    {shown.description}
                  </p>
                </section>
              )}

              <section className="space-y-2.5">
                <SectionLabel>Teknisyen</SectionLabel>
                {shown.assigned_user ? (
                  <div className="flex items-center gap-3">
                    <Avatar className="size-9">
                      <AvatarFallback className="bg-primary/10 text-xs font-semibold text-primary">
                        {initials(shown.assigned_user.name)}
                      </AvatarFallback>
                    </Avatar>
                    <div className="leading-tight">
                      <div className="text-sm font-medium text-foreground">
                        {shown.assigned_user.name}
                      </div>
                      <div className="text-xs text-muted-foreground">Saha Teknisyeni</div>
                    </div>
                  </div>
                ) : (
                  <div className="text-sm text-muted-foreground">Henüz atama yapılmadı.</div>
                )}
              </section>

              <section className="space-y-3">
                <SectionLabel>Zaman Çizelgesi</SectionLabel>
                <div>
                  <TimelineItem
                    icon={CalendarClock}
                    label="Planlandı"
                    value={shown.scheduled_at}
                    done={!!shown.scheduled_at}
                  />
                  <TimelineItem
                    icon={Play}
                    label="Çalışma Başladı"
                    value={shown.started_at}
                    done={!!shown.started_at}
                  />
                  <TimelineItem
                    icon={CheckCircle2}
                    label="Tamamlandı"
                    value={shown.completed_at}
                    done={!!shown.completed_at}
                    last
                  />
                </div>
              </section>

              {!detail && !actionError && (
                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                  <Loader2 className="size-4 animate-spin" />
                  Kontrol listesi yükleniyor...
                </div>
              )}

              {checklist.length > 0 && (
                <section className="space-y-3">
                  <div className="flex items-baseline justify-between">
                    <SectionLabel>Kontrol Listesi</SectionLabel>
                    <span className="text-xs tabular-nums text-muted-foreground">
                      {doneCount}/{checklist.length}
                    </span>
                  </div>
                  <div className="space-y-0.5">
                    {checklist.map((item) => (
                      <button
                        key={item.id}
                        type="button"
                        onClick={() => void toggleItem(item)}
                        className="flex w-full items-start gap-2.5 rounded-md px-2 py-1.5 text-left text-sm transition-colors hover:bg-muted/60"
                      >
                        {item.is_done ? (
                          <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-success" />
                        ) : (
                          <Circle className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                        )}
                        <span
                          className={cn(
                            "leading-5",
                            item.is_done && "text-muted-foreground line-through"
                          )}
                        >
                          {item.label}
                        </span>
                      </button>
                    ))}
                  </div>
                </section>
              )}

              {shown.notes && (
                <section className="space-y-2.5">
                  <SectionLabel>Notlar</SectionLabel>
                  <p className="rounded-lg border border-border bg-muted/40 p-4 text-sm leading-6 text-foreground">
                    {shown.notes}
                  </p>
                </section>
              )}
            </div>

            <div className="space-y-2 border-t border-border px-6 py-4">
              {actionError && (
                <p className="text-xs text-danger-foreground">{actionError}</p>
              )}

              {(quickAction || cancellable) && (
                <div className="flex gap-2">
                  {quickAction && (
                    <Button
                      className="flex-1"
                      disabled={isTransitioning}
                      onClick={() => void transition(quickAction.status)}
                    >
                      {isTransitioning ? (
                        <Loader2 className="animate-spin" />
                      ) : quickAction.status === "completed" ? (
                        <CheckCircle2 />
                      ) : (
                        <Play />
                      )}
                      {quickAction.label}
                    </Button>
                  )}
                  {cancellable &&
                    (confirmingCancel ? (
                      <>
                        <Button
                          variant="destructive"
                          className="flex-1"
                          disabled={isTransitioning}
                          onClick={() => void transition("cancelled")}
                        >
                          <Ban />
                          İptali Onayla
                        </Button>
                        <Button
                          variant="outline"
                          disabled={isTransitioning}
                          onClick={() => setConfirmingCancel(false)}
                        >
                          Vazgeç
                        </Button>
                      </>
                    ) : (
                      <Button
                        variant="outline"
                        className={cn("text-danger hover:text-danger", !quickAction && "flex-1")}
                        disabled={isTransitioning}
                        onClick={() => setConfirmingCancel(true)}
                      >
                        <Ban />
                        İptal Et
                      </Button>
                    ))}
                </div>
              )}

              <div className="flex gap-2">
                <Button variant="outline" className="flex-1" onClick={() => onEdit(shown)}>
                  <Pencil />
                  Düzenle
                </Button>
                <Button
                  variant="outline"
                  className="flex-1 text-danger hover:text-danger"
                  onClick={() => onDelete(shown)}
                >
                  <Trash2 />
                  Sil
                </Button>
              </div>
            </div>
          </>
        )}
      </SheetContent>
    </Sheet>
  );
}

const statusOptions = (Object.keys(workOrderStatusMeta) as WorkOrderStatus[]).map((s) => ({
  value: s,
  label: workOrderStatusMeta[s].label,
}));
const typeOptions = (Object.keys(workOrderTypeMeta) as WorkOrderType[]).map((t) => ({
  value: t,
  label: workOrderTypeMeta[t].label,
}));
const priorityOptions = (Object.keys(workOrderPriorityMeta) as WorkOrderPriority[]).map((p) => ({
  value: p,
  label: workOrderPriorityMeta[p].label,
}));

const UNASSIGNED = "__unassigned__";

interface WorkOrderFormValues {
  service_contract_uuid: string;
  type: WorkOrderType;
  status: WorkOrderStatus;
  priority: WorkOrderPriority;
  assigned_user_uuid: string;
  scheduled_at: string;
  started_at: string;
  completed_at: string;
  description: string;
  notes: string;
}

const emptyForm: WorkOrderFormValues = {
  service_contract_uuid: "",
  type: "maintenance",
  status: "draft",
  priority: "normal",
  assigned_user_uuid: UNASSIGNED,
  scheduled_at: "",
  started_at: "",
  completed_at: "",
  description: "",
  notes: "",
};

const blankToNull = (value: string): string | null => {
  const trimmed = value.trim();
  return trimmed === "" ? null : trimmed;
};

const pad = (n: number) => String(n).padStart(2, "0");

/** ISO datetime (UTC) → local "YYYY-MM-DDTHH:mm" for datetime-local inputs. */
function toLocalInput(value: string | null): string {
  if (!value) return "";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "";
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(
    date.getHours()
  )}:${pad(date.getMinutes())}`;
}

/** datetime-local value → ISO string (UTC), empty → null. */
function fromLocalInput(value: string): string | null {
  const trimmed = value.trim();
  if (trimmed === "") return null;
  const date = new Date(trimmed);
  return Number.isNaN(date.getTime()) ? null : date.toISOString();
}

function formFromWorkOrder(workOrder: WorkOrder | null): WorkOrderFormValues {
  if (!workOrder) return emptyForm;

  return {
    service_contract_uuid: workOrder.service_contract_id,
    type: workOrder.type,
    status: workOrder.status,
    priority: workOrder.priority,
    assigned_user_uuid: workOrder.assigned_user?.id ?? UNASSIGNED,
    scheduled_at: toLocalInput(workOrder.scheduled_at),
    started_at: toLocalInput(workOrder.started_at),
    completed_at: toLocalInput(workOrder.completed_at),
    description: workOrder.description ?? "",
    notes: workOrder.notes ?? "",
  };
}

function formToInput(values: WorkOrderFormValues): WorkOrderInput {
  return {
    service_contract_uuid: values.service_contract_uuid,
    type: values.type,
    status: values.status,
    priority: values.priority,
    assigned_user_uuid:
      values.assigned_user_uuid === UNASSIGNED ? null : values.assigned_user_uuid,
    scheduled_at: fromLocalInput(values.scheduled_at),
    started_at: fromLocalInput(values.started_at),
    completed_at: fromLocalInput(values.completed_at),
    description: blankToNull(values.description),
    notes: blankToNull(values.notes),
  };
}

function contractLabel(contract: ServiceContract): string {
  const number = contract.contract_number ?? "Sözleşme";
  return `${number} — ${contract.building_name} / ${contract.elevator_name}`;
}

function fieldError(errors: Record<string, string[]>, field: keyof WorkOrderFormValues) {
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

function WorkOrderFormDialog({
  open,
  workOrder,
  contracts,
  users,
  errors,
  formError,
  isSubmitting,
  onOpenChange,
  onSubmit,
}: {
  open: boolean;
  workOrder: WorkOrder | null;
  contracts: ServiceContract[];
  users: User[];
  errors: Record<string, string[]>;
  formError: string | null;
  isSubmitting: boolean;
  onOpenChange: (open: boolean) => void;
  onSubmit: (values: WorkOrderFormValues) => Promise<void>;
}) {
  const [values, setValues] = React.useState<WorkOrderFormValues>(() =>
    formFromWorkOrder(workOrder)
  );
  const isEditing = !!workOrder;

  React.useEffect(() => {
    if (open) setValues(formFromWorkOrder(workOrder));
  }, [workOrder, open]);

  const setValue = (field: keyof WorkOrderFormValues, value: string) => {
    setValues((prev) => ({ ...prev, [field]: value }));
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent
        className="max-h-[90vh] overflow-y-auto sm:max-w-2xl"
        onInteractOutside={(event) => event.preventDefault()}
      >
        <DialogHeader>
          <DialogTitle>{isEditing ? "İş Emrini Düzenle" : "Yeni İş Emri"}</DialogTitle>
          <DialogDescription>
            {isEditing ? (
              <>
                İş emri no:{" "}
                <span className="font-mono text-xs">{workOrder.work_order_number}</span>
              </>
            ) : (
              "Sözleşme, iş türü ve planlama bilgilerini gir. İş emri numarası kayıt sırasında otomatik oluşturulur."
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
          {formError && (
            <div className="rounded-md bg-danger-subtle px-3 py-2 text-sm text-danger-foreground">
              {formError}
            </div>
          )}

          <Field label="Sözleşme" error={fieldError(errors, "service_contract_uuid")}>
            <Select
              value={values.service_contract_uuid || undefined}
              onValueChange={(value) => setValue("service_contract_uuid", value)}
            >
              <SelectTrigger>
                <SelectValue placeholder="Sözleşme seç" />
              </SelectTrigger>
              <SelectContent>
                {contracts.map((contract) => (
                  <SelectItem key={contract.id} value={contract.id}>
                    {contractLabel(contract)}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </Field>

          <div className="grid gap-4 sm:grid-cols-3">
            <Field label="Tür" error={fieldError(errors, "type")}>
              <Select value={values.type} onValueChange={(value) => setValue("type", value)}>
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
            <Field label="Durum" error={fieldError(errors, "status")}>
              <Select value={values.status} onValueChange={(value) => setValue("status", value)}>
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
            <Field label="Öncelik" error={fieldError(errors, "priority")}>
              <Select
                value={values.priority}
                onValueChange={(value) => setValue("priority", value)}
              >
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {priorityOptions.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                      {option.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </Field>
          </div>

          <Field label="Teknisyen" error={fieldError(errors, "assigned_user_uuid")}>
            <Select
              value={values.assigned_user_uuid}
              onValueChange={(value) => setValue("assigned_user_uuid", value)}
            >
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value={UNASSIGNED}>Atanmadı</SelectItem>
                {users.map((user) => (
                  <SelectItem key={user.id} value={user.id}>
                    {user.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </Field>

          <div className="grid gap-4 sm:grid-cols-3">
            <Field label="Planlanan" error={fieldError(errors, "scheduled_at")}>
              <Input
                type="datetime-local"
                value={values.scheduled_at}
                onChange={(event) => setValue("scheduled_at", event.target.value)}
              />
            </Field>
            <Field label="Başlama" error={fieldError(errors, "started_at")}>
              <Input
                type="datetime-local"
                value={values.started_at}
                onChange={(event) => setValue("started_at", event.target.value)}
              />
            </Field>
            <Field label="Tamamlanma" error={fieldError(errors, "completed_at")}>
              <Input
                type="datetime-local"
                value={values.completed_at}
                onChange={(event) => setValue("completed_at", event.target.value)}
              />
            </Field>
          </div>

          <Field label="Açıklama" error={fieldError(errors, "description")}>
            <textarea
              className="min-h-20 w-full rounded-md border border-input bg-surface px-3 py-2 text-sm shadow-xs transition-colors placeholder:text-muted-foreground/70 focus-visible:border-ring focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
              value={values.description}
              onChange={(event) => setValue("description", event.target.value)}
              placeholder="Yapılacak işin detayları..."
            />
          </Field>

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
              {isEditing ? "Kaydet" : "İş Emri Oluştur"}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

export function WorkOrdersPage() {
  const [query, setQuery] = React.useState("");
  const [status, setStatus] = React.useState(ALL_VALUE);
  const [type, setType] = React.useState(ALL_VALUE);
  const [priority, setPriority] = React.useState(ALL_VALUE);
  const [page, setPage] = React.useState(1);
  const [selected, setSelected] = React.useState<WorkOrder | null>(null);
  const [formOpen, setFormOpen] = React.useState(false);
  const [editingWorkOrder, setEditingWorkOrder] = React.useState<WorkOrder | null>(null);
  const [deletingWorkOrder, setDeletingWorkOrder] = React.useState<WorkOrder | null>(null);
  const [formErrors, setFormErrors] = React.useState<Record<string, string[]>>({});
  const [formError, setFormError] = React.useState<string | null>(null);
  const [isSubmitting, setSubmitting] = React.useState(false);
  const [isDeleting, setDeleting] = React.useState(false);
  const debouncedQuery = useDebounced(query);

  React.useEffect(() => {
    setPage(1);
    setSelected(null);
  }, [debouncedQuery, status, type, priority]);

  const listParams = React.useMemo(
    () => ({
      page,
      perPage: 25,
      search: debouncedQuery,
      sort: "-scheduled_at",
      filter: {
        ...(status === ALL_VALUE ? {} : { status }),
        ...(type === ALL_VALUE ? {} : { type }),
        ...(priority === ALL_VALUE ? {} : { priority }),
      },
    }),
    [page, debouncedQuery, status, type, priority]
  );
  const { items: workOrders, pagination, isLoading, error, reload } = useList(
    fetchWorkOrders,
    listParams
  );
  const contractParams = React.useMemo(
    () => ({ perPage: 100, sort: "-start_date" }),
    []
  );
  const userParams = React.useMemo(() => ({ perPage: 100, sort: "name" }), []);
  const { items: contractOptions } = useList(fetchContracts, contractParams);
  const { items: userOptions } = useList(fetchUsers, userParams);

  const openCreate = () => {
    setEditingWorkOrder(null);
    setFormErrors({});
    setFormError(null);
    setFormOpen(true);
  };

  const openEdit = (workOrder: WorkOrder) => {
    setSelected(null);
    setEditingWorkOrder(workOrder);
    setFormErrors({});
    setFormError(null);
    setFormOpen(true);
  };

  const handleSubmit = async (values: WorkOrderFormValues) => {
    setSubmitting(true);
    setFormErrors({});
    setFormError(null);

    try {
      const input = formToInput(values);
      if (editingWorkOrder) {
        await updateWorkOrder(editingWorkOrder.id, input);
      } else {
        await createWorkOrder(input);
      }
      setFormOpen(false);
      setEditingWorkOrder(null);
      reload();
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
    if (!deletingWorkOrder) return;
    setDeleting(true);

    try {
      await deleteWorkOrder(deletingWorkOrder.id);
      setDeletingWorkOrder(null);
      reload();
    } finally {
      setDeleting(false);
    }
  };

  return (
    <div className="space-y-5">
      <PageHeader
        title="İş Emirleri"
        description="Bakım, arıza ve muayene işleri"
        count={pagination?.total ?? workOrders.length}
        actions={
          <Button onClick={openCreate}>
            <Plus />
            Yeni İş Emri
          </Button>
        }
      />

      <Toolbar>
        <SearchInput
          value={query}
          onChange={setQuery}
          placeholder="İş emri no, bina veya teknisyen ara..."
        />
        <FilterSelect
          value={status}
          onChange={setStatus}
          allLabel="Tüm Durumlar"
          options={statusOptions}
        />
        <FilterSelect value={type} onChange={setType} allLabel="Tüm Türler" options={typeOptions} />
        <FilterSelect
          value={priority}
          onChange={setPriority}
          allLabel="Tüm Öncelikler"
          options={priorityOptions}
        />
      </Toolbar>

      {error && <ListError message={error.message} onRetry={reload} />}

      <DataTable
        columns={columns}
        data={workOrders}
        getRowId={(wo) => wo.id}
        onRowClick={setSelected}
        isLoading={isLoading}
        rowActions={(workOrder) => (
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="ghost" size="icon-sm" aria-label="İş emri işlemleri">
                <MoreHorizontal />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuItem onSelect={() => openEdit(workOrder)}>
                <Pencil className="size-4" />
                Düzenle
              </DropdownMenuItem>
              <DropdownMenuItem
                className="text-danger focus:text-danger"
                onSelect={() => setDeletingWorkOrder(workOrder)}
              >
                <Trash2 className="size-4" />
                Sil
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        )}
        empty={
          <EmptyState
            icon={ClipboardList}
            title="İş emri bulunamadı"
            description="Arama veya filtre kriterlerine uyan iş emri yok."
          />
        }
      />

      <Pagination pagination={pagination} onPageChange={setPage} />

      <WorkOrderSheet
        workOrder={selected}
        onClose={() => setSelected(null)}
        onEdit={openEdit}
        onDelete={(workOrder) => {
          setSelected(null);
          setDeletingWorkOrder(workOrder);
        }}
        onChanged={reload}
      />

      <WorkOrderFormDialog
        open={formOpen}
        workOrder={editingWorkOrder}
        contracts={contractOptions}
        users={userOptions}
        errors={formErrors}
        formError={formError}
        isSubmitting={isSubmitting}
        onOpenChange={(open) => {
          setFormOpen(open);
          if (!open) setEditingWorkOrder(null);
        }}
        onSubmit={handleSubmit}
      />

      <Dialog
        open={!!deletingWorkOrder}
        onOpenChange={(open) => !open && setDeletingWorkOrder(null)}
      >
        <DialogContent>
          <DialogHeader>
            <DialogTitle>İş Emrini Sil</DialogTitle>
            <DialogDescription>
              {deletingWorkOrder
                ? `${deletingWorkOrder.work_order_number} numaralı iş emri silinecek.`
                : ""}{" "}
              Bu işlem kaydı liste görünümünden kaldırır.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button
              variant="outline"
              disabled={isDeleting}
              onClick={() => setDeletingWorkOrder(null)}
            >
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
