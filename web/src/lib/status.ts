/**
 * Single source of truth for domain status → label + visual treatment.
 * Every StatusBadge / indicator in the app reads from here so colors
 * stay consistent across screens.
 */
import type {
  ContractStatus,
  ElevatorStatus,
  WorkOrderPriority,
  WorkOrderStatus,
  WorkOrderType,
} from "@/types";

export type BadgeVariant =
  | "default"
  | "secondary"
  | "outline"
  | "success"
  | "warning"
  | "danger"
  | "info";

export interface StatusMeta {
  label: string;
  variant: BadgeVariant;
  /** tailwind text color for the leading dot indicator */
  dot: string;
}

export const elevatorStatusMeta: Record<ElevatorStatus, StatusMeta> = {
  active: { label: "Aktif", variant: "success", dot: "bg-success" },
  maintenance: { label: "Bakımda", variant: "warning", dot: "bg-warning" },
  inactive: { label: "Pasif", variant: "secondary", dot: "bg-muted-foreground" },
  out_of_service: { label: "Servis Dışı", variant: "danger", dot: "bg-danger" },
};

export const contractStatusMeta: Record<ContractStatus, StatusMeta> = {
  active: { label: "Aktif", variant: "success", dot: "bg-success" },
  expired: { label: "Süresi Doldu", variant: "danger", dot: "bg-danger" },
  suspended: { label: "Askıda", variant: "warning", dot: "bg-warning" },
  terminated: { label: "Feshedildi", variant: "secondary", dot: "bg-muted-foreground" },
};

export const workOrderStatusMeta: Record<WorkOrderStatus, StatusMeta> = {
  draft: { label: "Taslak", variant: "secondary", dot: "bg-muted-foreground" },
  planned: { label: "Planlandı", variant: "outline", dot: "bg-muted-foreground" },
  assigned: { label: "Atandı", variant: "info", dot: "bg-info" },
  in_progress: { label: "Devam Ediyor", variant: "info", dot: "bg-info" },
  completed: { label: "Tamamlandı", variant: "success", dot: "bg-success" },
  cancelled: { label: "İptal", variant: "danger", dot: "bg-danger" },
};

export const workOrderPriorityMeta: Record<WorkOrderPriority, StatusMeta> = {
  low: { label: "Düşük", variant: "secondary", dot: "bg-muted-foreground" },
  normal: { label: "Normal", variant: "info", dot: "bg-info" },
  high: { label: "Yüksek", variant: "warning", dot: "bg-warning" },
  critical: { label: "Kritik", variant: "danger", dot: "bg-danger" },
};

export const workOrderTypeMeta: Record<WorkOrderType, { label: string; icon: string }> = {
  maintenance: { label: "Periyodik Bakım", icon: "wrench" },
  fault: { label: "Arıza", icon: "alert-triangle" },
  inspection: { label: "Muayene", icon: "clipboard-check" },
  modernization: { label: "Modernizasyon", icon: "sparkles" },
  repair: { label: "Onarım", icon: "hammer" },
};
