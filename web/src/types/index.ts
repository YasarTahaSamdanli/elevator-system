/**
 * Domain types — mirror the Laravel backend models
 * (see backend/database/migrations & app/Models).
 * These describe API shapes; no runtime logic here.
 */

export type UUID = string;
export type ISODate = string; // "2026-01-15"
export type ISODateTime = string; // "2026-01-15T09:30:00Z"

// ---- Enums (match DB enum columns) ----

export type ElevatorStatus = "active" | "inactive" | "maintenance" | "out_of_service";

export type ContractStatus = "active" | "expired" | "suspended" | "terminated";

export type WorkOrderType =
  | "maintenance"
  | "fault"
  | "inspection"
  | "repair";

export type WorkOrderStatus =
  | "draft"
  | "planned"
  | "assigned"
  | "in_progress"
  | "completed"
  | "cancelled";

export type WorkOrderPriority = "low" | "normal" | "high" | "critical";

export type MaterialUnit = "piece" | "meter" | "kg" | "liter" | "set";

export type WarehouseType = "main" | "vehicle";

export type StockMovementType =
  | "purchase_in"
  | "work_order_out"
  | "work_order_return"
  | "transfer_in"
  | "transfer_out"
  | "adjustment_in"
  | "adjustment_out";

/** Periodic inspection label colors (Asansör Periyodik Kontrol Yönetmeliği). */
export type InspectionLabel = "green" | "blue" | "yellow" | "red";

export type InspectionType = "periodic" | "follow_up";

export type UserRole =
  | "Super Admin"
  | "Company Owner"
  | "Manager"
  | "Technician"
  | "Office Staff"
  | "Customer";

// ---- Models ----

export interface Company {
  id: UUID;
  name: string;
  tax_number: string | null;
  phone: string | null;
  email: string | null;
  city: string | null;
  district: string | null;
  is_active: boolean;
}

export interface User {
  id: UUID;
  company_id: UUID;
  name: string;
  email: string;
  phone: string | null;
  role: UserRole;
  is_active: boolean;
  avatar_url?: string | null;
}

export interface Building {
  id: UUID;
  company_id: UUID;
  name: string;
  code: string | null;
  address: string;
  city: string;
  district: string;
  manager_name: string | null;
  manager_phone: string | null;
  /** Kapı/dış kapı şifresi — teknisyen mobilde konum ekranında görür */
  entrance_code: string | null;
  access_notes: string | null;
  latitude: number | null;
  longitude: number | null;
  is_active: boolean;
  notes: string | null;
  /** Derived / aggregate helpers for list views */
  elevator_count: number;
}

export interface Elevator {
  id: UUID;
  building_id: UUID;
  building_name: string;
  serial_number: string;
  qr_identifier: string;
  name: string | null;
  manufacturer: string | null;
  model: string | null;
  installation_year: number | null;
  capacity_kg: number | null;
  person_capacity: number | null;
  stop_count: number | null;
  registration_number: string | null;
  status: ElevatorStatus;
  /** Snapshot of the latest inspection (server-maintained cache). */
  current_label: InspectionLabel | null;
  last_inspection_at: ISODate | null;
  next_inspection_due: ISODate | null;
  follow_up_due: ISODate | null;
  notes: string | null;
}

export interface InspectionFinding {
  id: UUID;
  description: string;
  is_resolved: boolean;
}

export interface ElevatorInspection {
  id: UUID;
  elevator_id: UUID;
  elevator_name: string;
  building_name: string;
  type: InspectionType;
  inspection_body: string | null;
  inspected_at: ISODate;
  label: InspectionLabel;
  report_number: string | null;
  follow_up_due_date: ISODate | null;
  next_inspection_date: ISODate | null;
  work_order: Pick<WorkOrder, "id" | "work_order_number" | "status"> | null;
  findings: InspectionFinding[];
  notes: string | null;
}

export type InspectionImportStatus =
  | "pending"
  | "imported"
  | "needs_review"
  | "ignored"
  | "failed";

export type InspectionImportReviewReason =
  | "parse_failed"
  | "no_text_layer"
  | "elevator_not_found"
  | "multiple_matches"
  | "duplicate_report";

/** A report PDF picked up from the RoyalCert mailbox (or uploaded manually). */
export interface InspectionImport {
  id: UUID;
  source: "email" | "upload";
  status: InspectionImportStatus;
  review_reason: InspectionImportReviewReason | null;
  error_message: string | null;
  /** Auto work-order guard failure (import itself succeeded). */
  work_order_error: string | null;
  mail_from: string | null;
  mail_subject: string | null;
  mail_received_at: string | null;
  original_filename: string | null;
  report_number: string | null;
  parsed_label: InspectionLabel | null;
  parsed_type: InspectionType | null;
  parsed_identity: string | null;
  parsed_findings: string[];
  parsed_warnings: string[];
  matched_via: string | null;
  elevator_id: UUID | null;
  elevator_name: string | null;
  building_name: string | null;
  inspection: {
    id: UUID;
    label: InspectionLabel;
    inspected_at: ISODate | null;
    work_order: Pick<WorkOrder, "id" | "work_order_number" | "status"> | null;
  } | null;
  created_at: string;
}

export interface ServiceContract {
  id: UUID;
  elevator_id: UUID;
  elevator_name: string;
  building_name: string;
  contract_number: string | null;
  start_date: ISODate;
  end_date: ISODate;
  status: ContractStatus;
  monthly_fee: number | null;
  notes: string | null;
}

/**
 * Defect colour from the inspection report (EK 7 colour sections);
 * null for checklist items that don't come from an inspection.
 */
export type ChecklistSeverity = "red" | "yellow" | "blue";

export interface WorkOrderChecklistItem {
  id: UUID;
  position: number;
  label: string;
  severity: ChecklistSeverity | null;
  item_code: string | null;
  is_done: boolean;
  note: string | null;
}

export interface WorkOrderItem {
  id: UUID;
  material: Pick<Material, "id" | "code" | "name" | "unit">;
  quantity: number;
  unit_price: number | null;
  total_price: number | null;
  note: string | null;
}

export interface WorkOrder {
  id: UUID;
  service_contract_id: UUID;
  work_order_number: string;
  type: WorkOrderType;
  status: WorkOrderStatus;
  priority: WorkOrderPriority;
  scheduled_at: ISODateTime | null;
  started_at: ISODateTime | null;
  completed_at: ISODateTime | null;
  assigned_user: Pick<User, "id" | "name" | "avatar_url"> | null;
  elevator_name: string;
  building_name: string;
  description: string | null;
  notes: string | null;
  /** Only present on detail responses (show/store/update). */
  checklist?: WorkOrderChecklistItem[];
  /** Only present on detail responses (show/store/update). */
  items?: WorkOrderItem[];
  created_at: ISODateTime;
  updated_at: ISODateTime;
}

export interface Material {
  id: UUID;
  code: string;
  name: string;
  unit: MaterialUnit;
  category: string | null;
  min_stock_level: number;
  default_unit_price: number | null;
  /** Müşteriye yansıtılan varsayılan satış fiyatı (maliyetten ayrı). */
  default_sale_price: number | null;
  stock_on_hand: number;
  is_active: boolean;
  notes: string | null;
}

export interface Warehouse {
  id: UUID;
  name: string;
  type: WarehouseType;
  user: Pick<User, "id" | "name"> | null;
  is_active: boolean;
}

export interface StockMovement {
  id: UUID;
  material: Pick<Material, "id" | "code" | "name" | "unit">;
  warehouse: Pick<Warehouse, "id" | "name" | "type">;
  type: StockMovementType;
  quantity: number;
  signed_quantity: number;
  unit_price: number | null;
  work_order: Pick<WorkOrder, "id" | "work_order_number"> | null;
  occurred_at: ISODateTime;
  created_by: Pick<User, "id" | "name"> | null;
  note: string | null;
}

export type AccountTransactionType =
  | "opening_balance"
  | "maintenance_fee"
  | "part_charge"
  | "revision_charge"
  | "adjustment_charge"
  | "payment"
  | "adjustment_credit";

export interface PaymentMethod {
  id: UUID;
  name: string;
  is_active: boolean;
}

export interface AccountTransaction {
  id: UUID;
  building: { id: UUID; name: string };
  elevator: { id: UUID; name: string | null; serial_number: string | null } | null;
  type: AccountTransactionType;
  amount: number;
  signed_amount: number;
  occurred_at: ISODate;
  work_order: { id: UUID; work_order_number: string } | null;
  payment_method: { id: UUID; name: string } | null;
  collected_by: { id: UUID; name: string } | null;
  payer_name: string | null;
  description: string | null;
  created_at: ISODateTime;
}

export interface AccountSummary {
  totals: Record<AccountTransactionType, number>;
  charges_total: number;
  credits_total: number;
  balance: number;
}

export interface AppNotification {
  id: UUID;
  title: string;
  body: string;
  type: "work_order" | "contract" | "elevator" | "system";
  created_at: ISODateTime;
  read: boolean;
}
