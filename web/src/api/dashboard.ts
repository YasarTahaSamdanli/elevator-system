/**
 * Dashboard aggregates and operational notifications — derived from the
 * existing CRUD list endpoints (there is no dedicated analytics or
 * notifications backend yet). Counts use `meta.pagination.total` from
 * narrow (perPage=1) requests instead of fetching everything and counting
 * client-side; charts fetch the underlying rows and group them in-browser.
 */
import { fetchContracts, fetchElevators, fetchInspectionImports, fetchMaterials, fetchStockMovements, fetchWorkOrders } from "@/api/resources";
import { workOrderTypeOrder } from "@/lib/chartColors";
import { formatDate } from "@/lib/format";
import { inspectionImportReviewReasonLabel, workOrderTypeMeta } from "@/lib/status";
import type { AppNotification, Material, StockMovement, WorkOrder, WorkOrderPriority } from "@/types";

const OPEN_STATUSES = ["draft", "planned", "assigned", "in_progress"];

const isoDate = (d: Date): string => d.toISOString().slice(0, 10);

function monthRange(monthOffset: number): { from: string; to: string } {
  const now = new Date();
  const first = new Date(now.getFullYear(), now.getMonth() + monthOffset, 1);
  const last = new Date(now.getFullYear(), now.getMonth() + monthOffset + 1, 0);
  return { from: isoDate(first), to: isoDate(last) };
}

function daysFromToday(offset: number): string {
  const d = new Date();
  d.setDate(d.getDate() + offset);
  return isoDate(d);
}

export interface DashboardStats {
  activeElevators: number;
  openWorkOrders: number;
  completedThisMonth: number;
  completedLastMonth: number;
  expiringContracts: number;
  inventoryValue: number;
  lowStockMaterials: number;
  monthlyConsumptionValue: number;
  stockMovementCount: number;
}

export async function fetchDashboardStats(): Promise<DashboardStats> {
  const thisMonth = monthRange(0);
  const lastMonth = monthRange(-1);

  const [activeElevators, openWorkOrders, completedThisMonth, completedLastMonth, expiringContracts, materials, monthlyOut, stockMovements] =
    await Promise.all([
      fetchElevators({ perPage: 1, filter: { status: "active" } }),
      fetchWorkOrders({ perPage: 1, filter: { status: OPEN_STATUSES } }),
      fetchWorkOrders({
        perPage: 1,
        filter: { status: "completed", completed_at_from: thisMonth.from, completed_at_to: thisMonth.to },
      }),
      fetchWorkOrders({
        perPage: 1,
        filter: { status: "completed", completed_at_from: lastMonth.from, completed_at_to: lastMonth.to },
      }),
      fetchContracts({
        perPage: 1,
        filter: { status: "active", end_date_from: daysFromToday(0), end_date_to: daysFromToday(30) },
      }),
      fetchMaterials({ perPage: 100, filter: { is_active: "true" } }),
      fetchStockMovements({
        perPage: 100,
        filter: { type: "work_order_out", occurred_at_from: thisMonth.from, occurred_at_to: thisMonth.to },
      }),
      fetchStockMovements({ perPage: 1 }),
    ]);

  const inventoryValue = materials.items.reduce(
    (sum, material) => sum + material.stock_on_hand * (material.default_unit_price ?? 0),
    0
  );
  const monthlyConsumptionValue = monthlyOut.items.reduce(
    (sum, movement) => sum + movement.quantity * (movement.unit_price ?? 0),
    0
  );

  return {
    activeElevators: activeElevators.pagination.total,
    openWorkOrders: openWorkOrders.pagination.total,
    completedThisMonth: completedThisMonth.pagination.total,
    completedLastMonth: completedLastMonth.pagination.total,
    expiringContracts: expiringContracts.pagination.total,
    inventoryValue,
    lowStockMaterials: materials.items.filter((material) => material.stock_on_hand < material.min_stock_level).length,
    monthlyConsumptionValue,
    stockMovementCount: stockMovements.pagination.total,
  };
}

/**
 * The four operational lanes the office actually works in (Bakım / Arıza /
 * Revizyon / Muayene) — mirrors the boss's old dashboard. Revizyon = repair
 * work orders opened from inspection findings; Muayene = RoyalCert periodic
 * and follow-up inspections.
 */
export interface OperationsSummary {
  maintenance: { open: number; completedThisMonth: number; scheduledToday: number };
  fault: { open: number; completedThisMonth: number };
  revision: { open: number; redLabeled: number; yellowLabeled: number };
  inspection: { dueThisMonth: number; followUpSoon: number; reportsToReview: number };
}

export async function fetchOperationsSummary(): Promise<OperationsSummary> {
  const thisMonth = monthRange(0);
  const today = daysFromToday(0);

  const openOfType = (type: string) =>
    fetchWorkOrders({ perPage: 1, filter: { type, status: OPEN_STATUSES } }).then(
      (r) => r.pagination.total
    );
  const completedOfType = (type: string) =>
    fetchWorkOrders({
      perPage: 1,
      filter: { type, status: "completed", completed_at_from: thisMonth.from, completed_at_to: thisMonth.to },
    }).then((r) => r.pagination.total);

  const [
    maintenanceOpen,
    maintenanceCompleted,
    maintenanceToday,
    faultOpen,
    faultCompleted,
    revisionOpen,
    redLabeled,
    yellowLabeled,
    dueThisMonth,
    followUpSoon,
    reportsToReview,
  ] = await Promise.all([
    openOfType("maintenance"),
    completedOfType("maintenance"),
    fetchWorkOrders({
      perPage: 1,
      filter: { type: "maintenance", status: OPEN_STATUSES, scheduled_at_from: today, scheduled_at_to: today },
    }).then((r) => r.pagination.total),
    openOfType("fault"),
    completedOfType("fault"),
    openOfType("repair"),
    fetchElevators({ perPage: 1, filter: { current_label: "red" } }).then((r) => r.pagination.total),
    fetchElevators({ perPage: 1, filter: { current_label: "yellow" } }).then((r) => r.pagination.total),
    fetchElevators({
      perPage: 1,
      filter: { next_inspection_due_from: thisMonth.from, next_inspection_due_to: thisMonth.to },
    }).then((r) => r.pagination.total),
    fetchElevators({ perPage: 1, filter: { follow_up_due_to: daysFromToday(15) } }).then(
      (r) => r.pagination.total
    ),
    fetchInspectionImports({ perPage: 1, filter: { status: "needs_review" } }).then(
      (r) => r.pagination.total
    ),
  ]);

  return {
    maintenance: {
      open: maintenanceOpen,
      completedThisMonth: maintenanceCompleted,
      scheduledToday: maintenanceToday,
    },
    fault: { open: faultOpen, completedThisMonth: faultCompleted },
    revision: { open: revisionOpen, redLabeled, yellowLabeled },
    inspection: { dueThisMonth, followUpSoon, reportsToReview },
  };
}

export interface VolumePoint {
  x: string;
  value: number;
}

/** Daily count of work orders opened in the last `days` days. */
export async function fetchWorkOrderVolume(days = 30): Promise<VolumePoint[]> {
  const from = new Date();
  from.setDate(from.getDate() - (days - 1));

  const { items } = await fetchWorkOrders({
    perPage: 100,
    sort: "created_at",
    filter: { created_at_from: isoDate(from), created_at_to: isoDate(new Date()) },
  });

  const counts = new Map<string, number>();
  for (let i = 0; i < days; i++) {
    const d = new Date(from);
    d.setDate(d.getDate() + i);
    counts.set(isoDate(d), 0);
  }
  for (const wo of items) {
    const day = wo.created_at.slice(0, 10);
    if (counts.has(day)) counts.set(day, (counts.get(day) ?? 0) + 1);
  }

  return [...counts.entries()].map(([x, value]) => ({ x, value }));
}

export interface TypeDistributionPoint {
  type: string;
  label: string;
  value: number;
}

/** Work-order counts by type over the last `days` days. */
export async function fetchWorkOrderTypeDistribution(days = 90): Promise<TypeDistributionPoint[]> {
  const from = daysFromToday(-days);

  const totals = await Promise.all(
    workOrderTypeOrder.map((type) =>
      fetchWorkOrders({ perPage: 1, filter: { type, created_at_from: from } }).then((r) => r.pagination.total)
    )
  );

  return workOrderTypeOrder
    .map((type, i) => ({ type, label: workOrderTypeMeta[type].label, value: totals[i] }))
    .filter((d) => d.value > 0);
}

const priorityRank: Record<WorkOrderPriority, number> = { critical: 0, high: 1, normal: 2, low: 3 };

/** Open work orders, most urgent first. */
export async function fetchTopOpenWorkOrders(limit = 5): Promise<WorkOrder[]> {
  const { items } = await fetchWorkOrders({
    perPage: 25,
    sort: "-scheduled_at",
    filter: { status: OPEN_STATUSES },
  });

  return [...items].sort((a, b) => priorityRank[a.priority] - priorityRank[b.priority]).slice(0, limit);
}

export async function fetchLowStockMaterials(limit = 5): Promise<Material[]> {
  const { items } = await fetchMaterials({ perPage: 100, sort: "code", filter: { is_active: "true" } });

  return items
    .filter((material) => material.stock_on_hand < material.min_stock_level)
    .slice(0, limit);
}

export interface InventoryMovementPoint {
  x: string;
  inValue: number;
  outValue: number;
}

export async function fetchInventoryMovementValue(days = 30): Promise<InventoryMovementPoint[]> {
  const from = new Date();
  from.setDate(from.getDate() - (days - 1));

  const { items } = await fetchStockMovements({
    perPage: 100,
    sort: "occurred_at",
    filter: { occurred_at_from: isoDate(from), occurred_at_to: isoDate(new Date()) },
  });

  const rows = new Map<string, InventoryMovementPoint>();
  for (let i = 0; i < days; i++) {
    const d = new Date(from);
    d.setDate(d.getDate() + i);
    const key = isoDate(d);
    rows.set(key, { x: key, inValue: 0, outValue: 0 });
  }

  for (const movement of items) {
    const day = movement.occurred_at.slice(0, 10);
    const row = rows.get(day);
    if (!row) continue;
    const value = movement.quantity * (movement.unit_price ?? 0);
    if (movement.signed_quantity >= 0) row.inValue += value;
    else row.outValue += value;
  }

  return [...rows.values()];
}

export interface TopConsumedMaterial {
  material: StockMovement["material"];
  quantity: number;
  value: number;
}

export async function fetchTopConsumedMaterials(limit = 5, days = 90): Promise<TopConsumedMaterial[]> {
  const { items } = await fetchStockMovements({
    perPage: 100,
    sort: "-occurred_at",
    filter: { type: "work_order_out", occurred_at_from: daysFromToday(-days) },
  });

  const totals = new Map<string, TopConsumedMaterial>();
  for (const movement of items) {
    const key = movement.material.id;
    const current = totals.get(key) ?? { material: movement.material, quantity: 0, value: 0 };
    current.quantity += movement.quantity;
    current.value += movement.quantity * (movement.unit_price ?? 0);
    totals.set(key, current);
  }

  return [...totals.values()].sort((a, b) => b.value - a.value).slice(0, limit);
}

export async function fetchRecentStockMovements(limit = 6): Promise<StockMovement[]> {
  const { items } = await fetchStockMovements({ perPage: limit, sort: "-occurred_at" });
  return items;
}

export interface ActivityItem {
  id: string;
  message: string;
  target: string;
  at: string;
  kind: "completed" | "created" | "assigned" | "progress" | "cancelled";
}

/**
 * Most recently touched work orders, described from their current status.
 * There is no audit/activity log backend, so this can only say *what* the
 * record's state implies happened — not *who* did it.
 */
export async function fetchRecentActivity(limit = 5): Promise<ActivityItem[]> {
  const { items } = await fetchWorkOrders({ perPage: limit, sort: "-updated_at" });

  return items.map((wo) => {
    const target = `${wo.building_name} · ${wo.elevator_name}`;

    if (wo.status === "completed") {
      return { id: wo.id, message: "iş emri tamamlandı", target, at: wo.updated_at, kind: "completed" as const };
    }
    if (wo.status === "cancelled") {
      return { id: wo.id, message: "iş emri iptal edildi", target, at: wo.updated_at, kind: "cancelled" as const };
    }
    if (wo.status === "in_progress") {
      return { id: wo.id, message: "üzerinde çalışılıyor", target, at: wo.updated_at, kind: "progress" as const };
    }
    if (wo.assigned_user) {
      return {
        id: wo.id,
        message: `${wo.assigned_user.name} atandı`,
        target,
        at: wo.updated_at,
        kind: "assigned" as const,
      };
    }
    return { id: wo.id, message: "iş emri oluşturuldu", target, at: wo.created_at, kind: "created" as const };
  });
}

/**
 * Notification-shaped feed derived from current operational state (urgent
 * open work orders, contracts expiring soon, elevators out of service).
 * There is no notifications table, so nothing here is persisted — "read"
 * state only lives in the browser tab for the session.
 */
export async function fetchOperationalNotifications(limit = 8): Promise<AppNotification[]> {
  const [urgent, expiring, down, materials, followUpDue, inspectionDue, importsToReview] = await Promise.all([
    fetchWorkOrders({
      perPage: 5,
      sort: "scheduled_at",
      filter: { status: OPEN_STATUSES, priority: ["critical", "high"] },
    }),
    fetchContracts({
      perPage: 5,
      sort: "end_date",
      filter: { status: "active", end_date_from: daysFromToday(0), end_date_to: daysFromToday(30) },
    }),
    fetchElevators({
      perPage: 5,
      sort: "-updated_at",
      filter: { status: ["maintenance", "out_of_service"] },
    }),
    fetchMaterials({ perPage: 100, sort: "code", filter: { is_active: "true" } }),
    // Red/yellow label follow-up deadlines within 15 days (or already overdue).
    fetchElevators({
      perPage: 5,
      sort: "follow_up_due",
      filter: { follow_up_due_to: daysFromToday(15) },
    }),
    // Periodic inspections due within 30 days.
    fetchElevators({
      perPage: 5,
      sort: "next_inspection_due",
      filter: { next_inspection_due_from: daysFromToday(0), next_inspection_due_to: daysFromToday(30) },
    }),
    // RoyalCert report imports parked in the manual review queue.
    fetchInspectionImports({ perPage: 5, sort: "-created_at", filter: { status: "needs_review" } }),
  ]);

  const now = new Date().toISOString();
  const today = now.slice(0, 10);

  const combined: AppNotification[] = [
    ...urgent.items.map((wo) => ({
      id: `wo-${wo.id}`,
      title: wo.priority === "critical" ? "Kritik iş emri" : "Yüksek öncelikli iş emri",
      body: `${wo.building_name} · ${wo.elevator_name} — ${workOrderTypeMeta[wo.type].label}`,
      type: "work_order" as const,
      created_at: wo.scheduled_at ?? wo.created_at,
      read: false,
    })),
    ...expiring.items.map((c) => ({
      id: `ctr-${c.id}`,
      title: "Sözleşme bitişi yaklaşıyor",
      body: `${c.building_name} · ${c.elevator_name} sözleşmesi ${formatDate(c.end_date)} tarihinde sona eriyor.`,
      type: "contract" as const,
      created_at: c.end_date,
      read: false,
    })),
    ...down.items.map((e) => ({
      id: `elv-${e.id}`,
      title: e.status === "out_of_service" ? "Asansör servis dışı" : "Asansör bakımda",
      body: `${e.building_name} · ${e.name ?? e.serial_number}`,
      type: "elevator" as const,
      created_at: now,
      read: false,
    })),
    ...followUpDue.items.map((e) => ({
      id: `insp-follow-${e.id}`,
      title:
        e.follow_up_due && e.follow_up_due < today
          ? "Etiket takip süresi doldu!"
          : "Etiket takip süresi doluyor",
      body: `${e.building_name} · ${e.name ?? e.serial_number} — ${
        e.current_label ? `${e.current_label === "red" ? "kırmızı" : "sarı"} etiket, ` : ""
      }takip kontrolü: ${e.follow_up_due ? formatDate(e.follow_up_due) : "—"}`,
      type: "elevator" as const,
      created_at: e.follow_up_due ?? now,
      read: false,
    })),
    ...inspectionDue.items.map((e) => ({
      id: `insp-next-${e.id}`,
      title: "Periyodik kontrol yaklaşıyor",
      body: `${e.building_name} · ${e.name ?? e.serial_number} — sonraki kontrol: ${
        e.next_inspection_due ? formatDate(e.next_inspection_due) : "—"
      }`,
      type: "elevator" as const,
      created_at: e.next_inspection_due ?? now,
      read: false,
    })),
    ...importsToReview.items.map((imp) => ({
      id: `imp-${imp.id}`,
      title: "İncelenmesi gereken muayene raporu",
      body: `${imp.mail_subject ?? imp.original_filename ?? "Rapor"}${
        imp.review_reason ? ` — ${inspectionImportReviewReasonLabel[imp.review_reason]}` : ""
      }`,
      type: "system" as const,
      created_at: imp.mail_received_at ?? imp.created_at,
      read: false,
    })),
    ...materials.items
      .filter((material: Material) => material.stock_on_hand < material.min_stock_level)
      .slice(0, 5)
      .map((material) => ({
        id: `mat-${material.id}`,
        title: "Minimum stok altı",
        body: `${material.code} · ${material.name}: ${material.stock_on_hand}/${material.min_stock_level}`,
        type: "system" as const,
        created_at: now,
        read: false,
      })),
  ];

  combined.sort((a, b) => (a.created_at < b.created_at ? 1 : -1));
  return combined.slice(0, limit);
}
