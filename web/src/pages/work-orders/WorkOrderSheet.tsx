import * as React from "react";
import {
  Ban,
  CalendarClock,
  CheckCircle2,
  Circle,
  Loader2,
  Package,
  Pencil,
  Play,
  Plus,
  Trash2,
  type LucideIcon,
} from "lucide-react";
import { StatusBadge } from "@/components/common/StatusBadge";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import { Button } from "@/components/ui/button";
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
  checklistSeverityMeta,
  workOrderPriorityMeta,
  workOrderStatusMeta,
  workOrderTypeMeta,
} from "@/lib/status";
import { formatCurrency, formatDateTime, formatNumber, initials } from "@/lib/format";
import { cn } from "@/lib/utils";
import {
  createWorkOrderItem,
  deleteWorkOrderItem,
  fetchMaterials,
  fetchWorkOrder,
  updateWorkOrderChecklistItem,
  updateWorkOrderStatus,
} from "@/api/resources";
import { useList } from "@/hooks/useList";
import { ApiError } from "@/lib/api";
import type {
  ChecklistSeverity,
  Material,
  WorkOrder,
  WorkOrderChecklistItem,
  WorkOrderStatus,
} from "@/types";
import { typeIcons } from "./work-order-meta";

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

/**
 * Group checklist items by defect colour, in the report's section order
 * (kırmızı → sarı → mavi), so the work order reads like the paper report.
 * Items without a severity (template checklists, hand-added) come last;
 * when no item has a severity the list renders flat, without headers.
 */
function checklistGroups(checklist: WorkOrderChecklistItem[]) {
  const order: (ChecklistSeverity | null)[] = ["red", "yellow", "blue", null];
  const hasSeverity = checklist.some((item) => item.severity !== null);

  return order
    .map((severity) => ({
      key: severity ?? "other",
      meta: severity && hasSeverity ? checklistSeverityMeta[severity] : null,
      items: checklist.filter((item) => item.severity === severity),
    }))
    .filter((group) => group.items.length > 0);
}

/** The forward transition offered as the primary quick action per status. */
const nextTransition: Partial<Record<WorkOrderStatus, { status: WorkOrderStatus; label: string }>> = {
  draft: { status: "in_progress", label: "Başlat" },
  planned: { status: "in_progress", label: "Başlat" },
  assigned: { status: "in_progress", label: "Başlat" },
  in_progress: { status: "completed", label: "Tamamla" },
};

export function WorkOrderSheet({
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
  const [confirmingComplete, setConfirmingComplete] = React.useState(false);
  const [itemMaterialId, setItemMaterialId] = React.useState("");
  const [itemQuantity, setItemQuantity] = React.useState("1");
  const [isSavingItem, setSavingItem] = React.useState(false);
  const materialParams = React.useMemo(
    () => ({ perPage: 100, sort: "code", filter: { is_active: "true" } }),
    []
  );
  const { items: materials } = useList(fetchMaterials, materialParams);

  const workOrderId = workOrder?.id;

  React.useEffect(() => {
    setDetail(null);
    setActionError(null);
    setConfirmingCancel(false);
    setConfirmingComplete(false);
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
  const workOrderItems = detail?.items ?? [];
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
      setConfirmingComplete(false);
      onChanged();
    } catch (err) {
      setActionError(err instanceof ApiError ? err.message : "Durum güncellenemedi.");
    } finally {
      setTransitioning(false);
    }
  };

  const addItem = async () => {
    if (!detail || !itemMaterialId) return;
    setSavingItem(true);
    setActionError(null);

    try {
      const item = await createWorkOrderItem(detail.id, {
        material_uuid: itemMaterialId,
        quantity: Number(itemQuantity),
        unit_price: null,
        note: null,
      });
      setDetail((prev) => (prev ? { ...prev, items: [...(prev.items ?? []), item] } : prev));
      setItemMaterialId("");
      setItemQuantity("1");
    } catch (err) {
      setActionError(err instanceof ApiError ? err.message : "Malzeme satırı eklenemedi.");
    } finally {
      setSavingItem(false);
    }
  };

  const removeItem = async (itemId: string) => {
    if (!detail) return;
    const previous = detail.items ?? [];

    setActionError(null);
    setDetail((prev) =>
      prev ? { ...prev, items: previous.filter((item) => item.id !== itemId) } : prev
    );

    try {
      await deleteWorkOrderItem(detail.id, itemId);
    } catch (err) {
      setDetail((prev) => (prev ? { ...prev, items: previous } : prev));
      setActionError(err instanceof ApiError ? err.message : "Malzeme satırı silinemedi.");
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
                  {checklistGroups(checklist).map((group) => (
                    <div key={group.key} className="space-y-0.5">
                      {group.meta && (
                        <div className="flex items-baseline justify-between px-2 pt-1">
                          <div className="flex items-center gap-2 text-xs font-semibold text-foreground">
                            <span className={cn("size-2 rounded-full", group.meta.dot)} />
                            {group.meta.label}
                          </div>
                          <span className="text-xs tabular-nums text-muted-foreground">
                            {group.items.filter((item) => item.is_done).length}/{group.items.length}
                          </span>
                        </div>
                      )}
                      {group.items.map((item) => (
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
                            {item.item_code && (
                              <span className="mr-1.5 font-mono text-xs text-muted-foreground">
                                {item.item_code}
                              </span>
                            )}
                            {item.label}
                          </span>
                        </button>
                      ))}
                    </div>
                  ))}
                </section>
              )}

              <section className="space-y-3">
                <div className="flex items-baseline justify-between">
                  <SectionLabel>Malzemeler</SectionLabel>
                  {workOrderItems.length > 0 && (
                    <span className="text-xs tabular-nums text-muted-foreground">
                      {formatCurrency(workOrderItems.reduce((sum, item) => sum + (item.total_price ?? 0), 0))}
                    </span>
                  )}
                </div>

                {workOrderItems.length === 0 ? (
                  <div className="rounded-md border border-dashed border-border px-3 py-4 text-sm text-muted-foreground">
                    Henüz malzeme satırı yok.
                  </div>
                ) : (
                  <div className="divide-y divide-border rounded-md border border-border">
                    {workOrderItems.map((item) => (
                      <div key={item.id} className="flex items-center gap-3 px-3 py-2.5">
                        <Package className="size-4 shrink-0 text-muted-foreground" />
                        <div className="min-w-0 flex-1">
                          <div className="truncate text-sm font-medium text-foreground">
                            {item.material.name}
                          </div>
                          <div className="text-xs text-muted-foreground">
                            {item.material.code} · {formatNumber(item.quantity)} {item.material.unit}
                            {item.unit_price != null ? ` · ${formatCurrency(item.unit_price)}` : ""}
                          </div>
                        </div>
                        <Button
                          variant="ghost"
                          size="icon-sm"
                          aria-label="Malzeme satırını sil"
                          onClick={() => void removeItem(item.id)}
                        >
                          <Trash2 className="size-4" />
                        </Button>
                      </div>
                    ))}
                  </div>
                )}

                {detail && shown.status !== "completed" && shown.status !== "cancelled" && (
                  <div className="grid gap-2 sm:grid-cols-[1fr_5rem_auto]">
                    <Select value={itemMaterialId || undefined} onValueChange={setItemMaterialId}>
                      <SelectTrigger>
                        <SelectValue placeholder="Malzeme seç" />
                      </SelectTrigger>
                      <SelectContent>
                        {materials.map((material: Material) => (
                          <SelectItem key={material.id} value={material.id}>
                            {material.code} · {material.name}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                    <Input
                      type="number"
                      min="0.001"
                      step="0.001"
                      value={itemQuantity}
                      onChange={(event) => setItemQuantity(event.target.value)}
                    />
                    <Button
                      type="button"
                      disabled={isSavingItem || !itemMaterialId}
                      onClick={() => void addItem()}
                    >
                      {isSavingItem ? <Loader2 className="animate-spin" /> : <Plus />}
                      Ekle
                    </Button>
                  </div>
                )}
              </section>

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

              {confirmingComplete && (
                <div className="rounded-md border border-border bg-muted/40 px-3 py-2 text-xs leading-5 text-muted-foreground">
                  {workOrderItems.length === 0
                    ? "Bu iş emrinde malzeme satırı yok — tamamlanınca stok düşümü yapılmayacak. Kullanılan malzeme varsa önce ekleyin; tamamlandıktan sonra satırlar kilitlenir."
                    : `${workOrderItems.length} malzeme satırı stoktan düşülecek. Tamamlandıktan sonra satırlar değiştirilemez.`}
                </div>
              )}

              {(quickAction || cancellable) && (
                <div className="flex gap-2">
                  {quickAction &&
                    (confirmingComplete ? (
                      <>
                        <Button
                          className="flex-1"
                          disabled={isTransitioning}
                          onClick={() => void transition("completed")}
                        >
                          {isTransitioning ? <Loader2 className="animate-spin" /> : <CheckCircle2 />}
                          Tamamlamayı Onayla
                        </Button>
                        <Button
                          variant="outline"
                          disabled={isTransitioning}
                          onClick={() => setConfirmingComplete(false)}
                        >
                          Vazgeç
                        </Button>
                      </>
                    ) : (
                      <Button
                        className="flex-1"
                        disabled={isTransitioning}
                        onClick={() => {
                          if (quickAction.status === "completed") {
                            setConfirmingCancel(false);
                            setConfirmingComplete(true);
                          } else {
                            void transition(quickAction.status);
                          }
                        }}
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
                    ))}
                  {cancellable &&
                    !confirmingComplete &&
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
