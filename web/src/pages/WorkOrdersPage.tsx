import * as React from "react";
import {
  AlertTriangle,
  CalendarClock,
  CheckCircle2,
  ClipboardCheck,
  ClipboardList,
  Hammer,
  Pencil,
  Play,
  Plus,
  Sparkles,
  Wrench,
  type LucideIcon,
} from "lucide-react";
import { PageHeader } from "@/components/common/PageHeader";
import { Toolbar } from "@/components/common/Toolbar";
import { SearchInput } from "@/components/common/SearchInput";
import { ALL_VALUE, FilterSelect } from "@/components/common/FilterSelect";
import { DataTable, type Column } from "@/components/common/DataTable";
import { EmptyState } from "@/components/common/EmptyState";
import { StatusBadge } from "@/components/common/StatusBadge";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import { Button } from "@/components/ui/button";
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
import { workOrders } from "@/mock";
import type {
  WorkOrder,
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

function WorkOrderSheet({
  workOrder,
  onClose,
}: {
  workOrder: WorkOrder | null;
  onClose: () => void;
}) {
  const TypeIcon = workOrder ? typeIcons[workOrder.type] : null;

  return (
    <Sheet open={!!workOrder} onOpenChange={(open) => !open && onClose()}>
      <SheetContent
        className="flex w-full flex-col gap-0 p-0 sm:max-w-lg"
        onOpenAutoFocus={(e) => e.preventDefault()}
      >
        {workOrder && TypeIcon && (
          <>
            <SheetHeader className="space-y-2 border-b border-border px-6 py-5 pr-14">
              <div className="flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                <TypeIcon className="size-3.5" />
                {workOrderTypeMeta[workOrder.type].label}
              </div>
              <SheetTitle className="font-mono text-lg">
                {workOrder.work_order_number}
              </SheetTitle>
              <SheetDescription>
                {workOrder.building_name} · {workOrder.elevator_name}
              </SheetDescription>
              <div className="flex flex-wrap gap-2 pt-1.5">
                <StatusBadge meta={workOrderStatusMeta[workOrder.status]} dot={false} />
                <StatusBadge meta={workOrderPriorityMeta[workOrder.priority]} />
              </div>
            </SheetHeader>

            <div className="flex-1 space-y-7 overflow-y-auto px-6 py-6">
              {workOrder.description && (
                <section className="space-y-2.5">
                  <SectionLabel>Açıklama</SectionLabel>
                  <p className="rounded-lg border border-border bg-muted/40 p-4 text-sm leading-6 text-foreground">
                    {workOrder.description}
                  </p>
                </section>
              )}

              <section className="space-y-2.5">
                <SectionLabel>Teknisyen</SectionLabel>
                {workOrder.assigned_user ? (
                  <div className="flex items-center gap-3">
                    <Avatar className="size-9">
                      <AvatarFallback className="bg-primary/10 text-xs font-semibold text-primary">
                        {initials(workOrder.assigned_user.name)}
                      </AvatarFallback>
                    </Avatar>
                    <div className="leading-tight">
                      <div className="text-sm font-medium text-foreground">
                        {workOrder.assigned_user.name}
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
                    value={workOrder.scheduled_at}
                    done={!!workOrder.scheduled_at}
                  />
                  <TimelineItem
                    icon={Play}
                    label="Çalışma Başladı"
                    value={workOrder.started_at}
                    done={!!workOrder.started_at}
                  />
                  <TimelineItem
                    icon={CheckCircle2}
                    label="Tamamlandı"
                    value={workOrder.completed_at}
                    done={!!workOrder.completed_at}
                    last
                  />
                </div>
              </section>
            </div>

            <div className="flex gap-2 border-t border-border px-6 py-4">
              <Button className="flex-1">Durumu Güncelle</Button>
              <Button variant="outline" className="flex-1">
                <Pencil />
                Düzenle
              </Button>
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

export function WorkOrdersPage() {
  const [query, setQuery] = React.useState("");
  const [status, setStatus] = React.useState(ALL_VALUE);
  const [type, setType] = React.useState(ALL_VALUE);
  const [priority, setPriority] = React.useState(ALL_VALUE);
  const [selected, setSelected] = React.useState<WorkOrder | null>(null);

  const filtered = workOrders.filter((wo) => {
    if (status !== ALL_VALUE && wo.status !== status) return false;
    if (type !== ALL_VALUE && wo.type !== type) return false;
    if (priority !== ALL_VALUE && wo.priority !== priority) return false;
    if (!query) return true;
    const q = query.toLocaleLowerCase("tr-TR");
    return [wo.work_order_number, wo.building_name, wo.elevator_name, wo.assigned_user?.name]
      .filter(Boolean)
      .some((v) => v!.toLocaleLowerCase("tr-TR").includes(q));
  });

  return (
    <div className="space-y-5">
      <PageHeader
        title="İş Emirleri"
        description="Bakım, arıza ve muayene işleri"
        count={workOrders.length}
        actions={
          <Button>
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

      <DataTable
        columns={columns}
        data={filtered}
        getRowId={(wo) => wo.id}
        onRowClick={setSelected}
        empty={
          <EmptyState
            icon={ClipboardList}
            title="İş emri bulunamadı"
            description="Arama veya filtre kriterlerine uyan iş emri yok."
          />
        }
      />

      <WorkOrderSheet workOrder={selected} onClose={() => setSelected(null)} />
    </div>
  );
}
