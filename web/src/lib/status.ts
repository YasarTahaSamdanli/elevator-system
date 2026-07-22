/**
 * Single source of truth for domain status → label + visual treatment.
 * Every StatusBadge / indicator in the app reads from here so colors
 * stay consistent across screens.
 */
import type {
  AccountTransactionType,
  ChecklistSeverity,
  ContractStatus,
  ElevatorStatus,
  InspectionImportReviewReason,
  InspectionImportStatus,
  InspectionLabel,
  InspectionType,
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

export const inspectionLabelMeta: Record<InspectionLabel, StatusMeta> = {
  green: { label: "Yeşil", variant: "success", dot: "bg-success" },
  blue: { label: "Mavi", variant: "info", dot: "bg-info" },
  yellow: { label: "Sarı", variant: "warning", dot: "bg-warning" },
  red: { label: "Kırmızı", variant: "danger", dot: "bg-danger" },
};

/**
 * Checklist groups mirror the report's colour sections (EK 7): the work
 * order is worked through in the same order as the paper.
 */
export const checklistSeverityMeta: Record<
  ChecklistSeverity,
  { label: string; dot: string }
> = {
  red: { label: "Kırmızı Eksikler", dot: "bg-danger" },
  yellow: { label: "Sarı Eksikler", dot: "bg-warning" },
  blue: { label: "Mavi Eksikler", dot: "bg-info" },
};

export const inspectionTypeMeta: Record<InspectionType, StatusMeta> = {
  periodic: { label: "Periyodik", variant: "outline", dot: "bg-muted-foreground" },
  follow_up: { label: "Takip", variant: "secondary", dot: "bg-muted-foreground" },
};

export const inspectionImportStatusMeta: Record<InspectionImportStatus, StatusMeta> = {
  pending: { label: "Bekliyor", variant: "outline", dot: "bg-muted-foreground" },
  imported: { label: "İçe Aktarıldı", variant: "success", dot: "bg-success" },
  needs_review: { label: "İnceleme Gerekli", variant: "warning", dot: "bg-warning" },
  ignored: { label: "Yoksayıldı", variant: "secondary", dot: "bg-muted-foreground" },
  failed: { label: "Hata", variant: "danger", dot: "bg-danger" },
};

export const inspectionImportReviewReasonLabel: Record<InspectionImportReviewReason, string> = {
  parse_failed: "Rapor okunamadı",
  no_text_layer: "PDF metin içermiyor (taranmış olabilir)",
  elevator_not_found: "Asansör eşleşmedi",
  multiple_matches: "Birden fazla asansör eşleşti",
  duplicate_report: "Aynı rapor numarası zaten kayıtlı",
};

export const accountTransactionTypeMeta: Record<AccountTransactionType, StatusMeta> = {
  opening_balance: { label: "Devir", variant: "secondary", dot: "bg-muted-foreground" },
  maintenance_fee: { label: "Bakım Ücreti", variant: "info", dot: "bg-info" },
  part_charge: { label: "Parça", variant: "warning", dot: "bg-warning" },
  revision_charge: { label: "Revizyon", variant: "warning", dot: "bg-warning" },
  adjustment_charge: { label: "Düzeltme (Borç)", variant: "outline", dot: "bg-muted-foreground" },
  payment: { label: "Tahsilat", variant: "success", dot: "bg-success" },
  adjustment_credit: { label: "Düzeltme (Alacak)", variant: "outline", dot: "bg-muted-foreground" },
};

export const workOrderTypeMeta: Record<WorkOrderType, { label: string; icon: string }> = {
  maintenance: { label: "Periyodik Bakım", icon: "wrench" },
  fault: { label: "Arıza", icon: "alert-triangle" },
  inspection: { label: "Muayene", icon: "clipboard-check" },
  repair: { label: "Revizyon", icon: "hammer" },
};
