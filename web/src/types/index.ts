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
  | "modernization"
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
  notes: string | null;
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

export interface WorkOrderChecklistItem {
  id: UUID;
  position: number;
  label: string;
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

export interface AppNotification {
  id: UUID;
  title: string;
  body: string;
  type: "work_order" | "contract" | "elevator" | "system";
  created_at: ISODateTime;
  read: boolean;
}
