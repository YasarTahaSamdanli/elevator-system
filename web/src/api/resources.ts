/**
 * Typed fetchers for the backend resources. Raw API payloads (uuid keys,
 * nested relation objects) are mapped onto the UI domain types in
 * `@/types`, so pages and components stay unchanged.
 */
import { api, listQueryString, type ListParams, type PaginationMeta } from "@/lib/api";
import type {
  Building,
  Elevator,
  Material,
  MaterialUnit,
  ServiceContract,
  StockMovement,
  User,
  UserRole,
  Warehouse,
  WorkOrder,
} from "@/types";

export interface ListResult<T> {
  items: T[];
  pagination: PaginationMeta;
}

const emptyPagination: PaginationMeta = { page: 1, per_page: 25, total: 0, total_pages: 1 };

/* ---------- raw payload shapes (backend Resources) ---------- */

interface Ref {
  uuid: string | null;
  name: string | null;
}

interface BuildingPayload {
  uuid: string;
  name: string;
  code: string | null;
  address: string;
  city: string;
  district: string;
  manager_name: string | null;
  manager_phone: string | null;
  latitude: string | number | null;
  longitude: string | number | null;
  is_active: boolean;
  notes: string | null;
  elevator_count?: number;
}

export interface BuildingInput {
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
}

interface ElevatorPayload {
  uuid: string;
  qr_identifier: string;
  building: Ref;
  serial_number: string;
  name: string | null;
  manufacturer: string | null;
  model: string | null;
  installation_year: number | null;
  capacity_kg: number | null;
  person_capacity: number | null;
  stop_count: number | null;
  registration_number: string | null;
  status: Elevator["status"];
  notes: string | null;
}

interface ContractPayload {
  uuid: string;
  elevator: Ref & { serial_number: string | null; building: Ref };
  contract_number: string | null;
  start_date: string;
  end_date: string;
  status: ServiceContract["status"];
  monthly_fee: string | number | null;
  notes: string | null;
}

interface ChecklistItemPayload {
  uuid: string;
  position: number;
  label: string;
  is_done: boolean;
  note: string | null;
}

interface WorkOrderPayload {
  uuid: string;
  work_order_number: string;
  service_contract: { uuid: string | null; contract_number: string | null };
  elevator: Ref & { serial_number: string | null };
  building: Ref;
  assigned_user?: { uuid: string | null; name: string | null } | null;
  type: WorkOrder["type"];
  status: WorkOrder["status"];
  priority: WorkOrder["priority"];
  scheduled_at: string | null;
  started_at: string | null;
  completed_at: string | null;
  description: string | null;
  notes: string | null;
  /** Only included on detail responses (show/store/update). */
  checklist?: ChecklistItemPayload[];
  /** Only included on detail responses (show/store/update). */
  items?: WorkOrderItemPayload[];
  created_at: string;
  updated_at: string;
}

interface UserPayload {
  uuid: string;
  name: string;
  email: string;
  phone: string | null;
  is_active: boolean;
  roles: string[];
}

interface MaterialPayload {
  uuid: string;
  code: string;
  name: string;
  unit: MaterialUnit;
  category: string | null;
  min_stock_level: string | number;
  default_unit_price: string | number | null;
  stock_on_hand?: string | number;
  is_active: boolean;
  notes: string | null;
}

interface WarehousePayload {
  uuid: string;
  name: string;
  type: Warehouse["type"];
  user?: { uuid: string | null; name: string | null } | null;
  is_active: boolean;
}

interface StockMovementPayload {
  uuid: string;
  material: { uuid: string | null; code: string | null; name: string | null; unit: MaterialUnit | null };
  warehouse: { uuid: string | null; name: string | null; type: Warehouse["type"] | null };
  type: StockMovement["type"];
  quantity: string | number;
  signed_quantity: string | number;
  unit_price: string | number | null;
  work_order?: { uuid: string | null; work_order_number: string | null } | null;
  occurred_at: string;
  created_by?: { uuid: string | null; name: string | null } | null;
  note: string | null;
}

interface WorkOrderItemPayload {
  uuid: string;
  material: { uuid: string | null; code: string | null; name: string | null; unit: MaterialUnit | null };
  quantity: string | number;
  unit_price: string | number | null;
  total_price: string | number | null;
  note: string | null;
}

/* ---------- mappers ---------- */

const num = (v: string | number | null): number | null => (v == null ? null : Number(v));

function mapBuilding(p: BuildingPayload): Building {
  return {
    id: p.uuid,
    company_id: "",
    name: p.name,
    code: p.code,
    address: p.address,
    city: p.city,
    district: p.district,
    manager_name: p.manager_name,
    manager_phone: p.manager_phone,
    latitude: num(p.latitude),
    longitude: num(p.longitude),
    is_active: p.is_active,
    notes: p.notes,
    elevator_count: p.elevator_count ?? 0,
  };
}

function mapElevator(p: ElevatorPayload): Elevator {
  return {
    id: p.uuid,
    building_id: p.building.uuid ?? "",
    building_name: p.building.name ?? "—",
    serial_number: p.serial_number,
    qr_identifier: p.qr_identifier,
    name: p.name,
    manufacturer: p.manufacturer,
    model: p.model,
    installation_year: p.installation_year,
    capacity_kg: p.capacity_kg,
    person_capacity: p.person_capacity,
    stop_count: p.stop_count,
    registration_number: p.registration_number,
    status: p.status,
    notes: p.notes,
  };
}

function mapContract(p: ContractPayload): ServiceContract {
  return {
    id: p.uuid,
    elevator_id: p.elevator.uuid ?? "",
    elevator_name: p.elevator.name ?? p.elevator.serial_number ?? "—",
    building_name: p.elevator.building.name ?? "—",
    contract_number: p.contract_number,
    start_date: p.start_date,
    end_date: p.end_date,
    status: p.status,
    monthly_fee: num(p.monthly_fee),
    notes: p.notes,
  };
}

function mapWorkOrder(p: WorkOrderPayload): WorkOrder {
  return {
    id: p.uuid,
    service_contract_id: p.service_contract.uuid ?? "",
    work_order_number: p.work_order_number,
    type: p.type,
    status: p.status,
    priority: p.priority,
    scheduled_at: p.scheduled_at,
    started_at: p.started_at,
    completed_at: p.completed_at,
    assigned_user: p.assigned_user?.uuid
      ? { id: p.assigned_user.uuid, name: p.assigned_user.name ?? "—", avatar_url: null }
      : null,
    elevator_name: p.elevator.name ?? p.elevator.serial_number ?? "—",
    building_name: p.building.name ?? "—",
    description: p.description,
    notes: p.notes,
    checklist: p.checklist?.map((item) => ({
      id: item.uuid,
      position: item.position,
      label: item.label,
      is_done: item.is_done,
      note: item.note,
    })),
    items: p.items?.map(mapWorkOrderItem),
    created_at: p.created_at,
    updated_at: p.updated_at,
  };
}

function mapMaterial(p: MaterialPayload): Material {
  return {
    id: p.uuid,
    code: p.code,
    name: p.name,
    unit: p.unit,
    category: p.category,
    min_stock_level: Number(p.min_stock_level),
    default_unit_price: num(p.default_unit_price),
    stock_on_hand: Number(p.stock_on_hand ?? 0),
    is_active: p.is_active,
    notes: p.notes,
  };
}

function mapWarehouse(p: WarehousePayload): Warehouse {
  return {
    id: p.uuid,
    name: p.name,
    type: p.type,
    user: p.user?.uuid ? { id: p.user.uuid, name: p.user.name ?? "—" } : null,
    is_active: p.is_active,
  };
}

function mapStockMovement(p: StockMovementPayload): StockMovement {
  return {
    id: p.uuid,
    material: {
      id: p.material.uuid ?? "",
      code: p.material.code ?? "",
      name: p.material.name ?? "—",
      unit: p.material.unit ?? "piece",
    },
    warehouse: {
      id: p.warehouse.uuid ?? "",
      name: p.warehouse.name ?? "—",
      type: p.warehouse.type ?? "main",
    },
    type: p.type,
    quantity: Number(p.quantity),
    signed_quantity: Number(p.signed_quantity),
    unit_price: num(p.unit_price),
    work_order: p.work_order?.uuid
      ? { id: p.work_order.uuid, work_order_number: p.work_order.work_order_number ?? "—" }
      : null,
    occurred_at: p.occurred_at,
    created_by: p.created_by?.uuid ? { id: p.created_by.uuid, name: p.created_by.name ?? "—" } : null,
    note: p.note,
  };
}

function mapWorkOrderItem(p: WorkOrderItemPayload): NonNullable<WorkOrder["items"]>[number] {
  return {
    id: p.uuid,
    material: {
      id: p.material.uuid ?? "",
      code: p.material.code ?? "",
      name: p.material.name ?? "—",
      unit: p.material.unit ?? "piece",
    },
    quantity: Number(p.quantity),
    unit_price: num(p.unit_price),
    total_price: num(p.total_price),
    note: p.note,
  };
}

function mapUser(p: UserPayload): User {
  return {
    id: p.uuid,
    company_id: "",
    name: p.name,
    email: p.email,
    phone: p.phone,
    role: (p.roles[0] ?? "Customer") as UserRole,
    is_active: p.is_active,
    avatar_url: null,
  };
}

/* ---------- list fetchers ---------- */

async function fetchList<P, T>(
  path: string,
  params: ListParams,
  mapper: (p: P) => T
): Promise<ListResult<T>> {
  const { data, meta } = await api<P[]>(`${path}${listQueryString(params)}`);
  return {
    items: data.map(mapper),
    pagination: meta?.pagination ?? { ...emptyPagination, total: data.length },
  };
}

export const fetchBuildings = (params: ListParams = {}) =>
  fetchList<BuildingPayload, Building>("/buildings", params, mapBuilding);

export async function createBuilding(input: BuildingInput): Promise<Building> {
  const { data } = await api<BuildingPayload>("/buildings", {
    method: "POST",
    body: input,
  });
  return mapBuilding(data);
}

export async function updateBuilding(uuid: string, input: BuildingInput): Promise<Building> {
  const { data } = await api<BuildingPayload>(`/buildings/${uuid}`, {
    method: "PUT",
    body: input,
  });
  return mapBuilding(data);
}

export async function deleteBuilding(uuid: string): Promise<void> {
  await api(`/buildings/${uuid}`, { method: "DELETE" });
}

export const fetchElevators = (params: ListParams = {}) =>
  fetchList<ElevatorPayload, Elevator>("/elevators", params, mapElevator);

export interface ElevatorInput {
  building_uuid: string;
  serial_number: string;
  name: string | null;
  manufacturer: string | null;
  model: string | null;
  installation_year: number | null;
  capacity_kg: number | null;
  person_capacity: number | null;
  stop_count: number | null;
  registration_number: string | null;
  status: Elevator["status"];
  notes: string | null;
}

export async function createElevator(input: ElevatorInput): Promise<Elevator> {
  const { data } = await api<ElevatorPayload>("/elevators", {
    method: "POST",
    body: input,
  });
  return mapElevator(data);
}

export async function updateElevator(uuid: string, input: ElevatorInput): Promise<Elevator> {
  const { data } = await api<ElevatorPayload>(`/elevators/${uuid}`, {
    method: "PUT",
    body: input,
  });
  return mapElevator(data);
}

export async function deleteElevator(uuid: string): Promise<void> {
  await api(`/elevators/${uuid}`, { method: "DELETE" });
}

export const fetchContracts = (params: ListParams = {}) =>
  fetchList<ContractPayload, ServiceContract>("/service-contracts", params, mapContract);

export interface ContractInput {
  elevator_uuid: string;
  contract_number: string | null;
  start_date: string;
  end_date: string;
  status: ServiceContract["status"];
  monthly_fee: number | null;
  notes: string | null;
}

export async function createContract(input: ContractInput): Promise<ServiceContract> {
  const { data } = await api<ContractPayload>("/service-contracts", {
    method: "POST",
    body: input,
  });
  return mapContract(data);
}

export async function updateContract(uuid: string, input: ContractInput): Promise<ServiceContract> {
  const { data } = await api<ContractPayload>(`/service-contracts/${uuid}`, {
    method: "PUT",
    body: input,
  });
  return mapContract(data);
}

export async function deleteContract(uuid: string): Promise<void> {
  await api(`/service-contracts/${uuid}`, { method: "DELETE" });
}

export const fetchWorkOrders = (params: ListParams = {}) =>
  fetchList<WorkOrderPayload, WorkOrder>("/work-orders", params, mapWorkOrder);

export async function fetchWorkOrder(uuid: string): Promise<WorkOrder> {
  const { data } = await api<WorkOrderPayload>(`/work-orders/${uuid}`);
  return mapWorkOrder(data);
}

/** Partial update used by the quick status-transition buttons. */
export async function updateWorkOrderStatus(
  uuid: string,
  status: WorkOrder["status"]
): Promise<WorkOrder> {
  const { data } = await api<WorkOrderPayload>(`/work-orders/${uuid}`, {
    method: "PUT",
    body: { status },
  });
  return mapWorkOrder(data);
}

export async function updateWorkOrderChecklistItem(
  workOrderUuid: string,
  itemUuid: string,
  body: { is_done?: boolean; note?: string | null }
): Promise<void> {
  await api(`/work-orders/${workOrderUuid}/checklist-items/${itemUuid}`, {
    method: "PATCH",
    body,
  });
}

export interface WorkOrderItemInput {
  material_uuid: string;
  quantity: number;
  unit_price: number | null;
  note: string | null;
}

export async function createWorkOrderItem(
  workOrderUuid: string,
  input: WorkOrderItemInput
): Promise<NonNullable<WorkOrder["items"]>[number]> {
  const { data } = await api<WorkOrderItemPayload>(`/work-orders/${workOrderUuid}/items`, {
    method: "POST",
    body: input,
  });
  return mapWorkOrderItem(data);
}

export async function deleteWorkOrderItem(workOrderUuid: string, itemUuid: string): Promise<void> {
  await api(`/work-orders/${workOrderUuid}/items/${itemUuid}`, { method: "DELETE" });
}

export interface WorkOrderInput {
  service_contract_uuid: string;
  type: WorkOrder["type"];
  status: WorkOrder["status"];
  priority: WorkOrder["priority"];
  scheduled_at: string | null;
  started_at: string | null;
  completed_at: string | null;
  assigned_user_uuid: string | null;
  description: string | null;
  notes: string | null;
}

export async function createWorkOrder(input: WorkOrderInput): Promise<WorkOrder> {
  const { data } = await api<WorkOrderPayload>("/work-orders", {
    method: "POST",
    body: input,
  });
  return mapWorkOrder(data);
}

export async function updateWorkOrder(uuid: string, input: WorkOrderInput): Promise<WorkOrder> {
  const { data } = await api<WorkOrderPayload>(`/work-orders/${uuid}`, {
    method: "PUT",
    body: input,
  });
  return mapWorkOrder(data);
}

export async function deleteWorkOrder(uuid: string): Promise<void> {
  await api(`/work-orders/${uuid}`, { method: "DELETE" });
}

export const fetchMaterials = (params: ListParams = {}) =>
  fetchList<MaterialPayload, Material>("/materials", params, mapMaterial);

export interface MaterialInput {
  code: string;
  name: string;
  unit: MaterialUnit;
  category: string | null;
  min_stock_level: number;
  default_unit_price: number | null;
  is_active: boolean;
  notes: string | null;
}

export async function createMaterial(input: MaterialInput): Promise<Material> {
  const { data } = await api<MaterialPayload>("/materials", {
    method: "POST",
    body: input,
  });
  return mapMaterial(data);
}

export async function updateMaterial(uuid: string, input: MaterialInput): Promise<Material> {
  const { data } = await api<MaterialPayload>(`/materials/${uuid}`, {
    method: "PUT",
    body: input,
  });
  return mapMaterial(data);
}

export async function deleteMaterial(uuid: string): Promise<void> {
  await api(`/materials/${uuid}`, { method: "DELETE" });
}

export const fetchWarehouses = (params: ListParams = {}) =>
  fetchList<WarehousePayload, Warehouse>("/warehouses", params, mapWarehouse);

export interface WarehouseInput {
  name: string;
  type: Warehouse["type"];
  user_uuid: string | null;
  is_active: boolean;
}

export async function createWarehouse(input: WarehouseInput): Promise<Warehouse> {
  const { data } = await api<WarehousePayload>("/warehouses", {
    method: "POST",
    body: input,
  });
  return mapWarehouse(data);
}

export async function updateWarehouse(uuid: string, input: WarehouseInput): Promise<Warehouse> {
  const { data } = await api<WarehousePayload>(`/warehouses/${uuid}`, {
    method: "PUT",
    body: input,
  });
  return mapWarehouse(data);
}

export const fetchStockMovements = (params: ListParams = {}) =>
  fetchList<StockMovementPayload, StockMovement>("/stock-movements", params, mapStockMovement);

export interface StockMovementInput {
  material_uuid: string;
  warehouse_uuid: string;
  type: StockMovement["type"];
  quantity: number;
  unit_price: number | null;
  occurred_at: string | null;
  note: string | null;
  update_material_price?: boolean;
}

export async function createStockMovement(input: StockMovementInput): Promise<StockMovement> {
  const { data } = await api<StockMovementPayload>("/stock-movements", {
    method: "POST",
    body: input,
  });
  return mapStockMovement(data);
}

export interface StockTransferInput {
  material_uuid: string;
  from_warehouse_uuid: string;
  to_warehouse_uuid: string;
  quantity: number;
  occurred_at?: string | null;
  note: string | null;
}

export async function createStockTransfer(input: StockTransferInput): Promise<StockMovement[]> {
  const { data } = await api<StockMovementPayload[]>("/stock-movements/transfers", {
    method: "POST",
    body: input,
  });
  return data.map(mapStockMovement);
}

export const fetchUsers = (params: ListParams = {}) =>
  fetchList<UserPayload, User>("/users", params, mapUser);

export interface UserInput {
  name: string;
  email: string;
  phone: string | null;
  password?: string;
  role: UserRole;
  is_active: boolean;
}

export async function createUser(input: UserInput & { password: string }): Promise<User> {
  const { data } = await api<UserPayload>("/users", {
    method: "POST",
    body: input,
  });
  return mapUser(data);
}

export async function updateUser(uuid: string, input: UserInput): Promise<User> {
  const body = { ...input };
  if (!body.password) delete body.password;

  const { data } = await api<UserPayload>(`/users/${uuid}`, {
    method: "PUT",
    body,
  });
  return mapUser(data);
}

export async function deleteUser(uuid: string): Promise<void> {
  await api(`/users/${uuid}`, { method: "DELETE" });
}

/* ---------- auth ---------- */

export interface AuthUser {
  uuid: string;
  name: string;
  email: string;
  company: { uuid: string | null; name: string | null };
  roles: string[];
}

export async function apiLogin(email: string, password: string): Promise<string> {
  const { data } = await api<{ token: string }>("/login", {
    method: "POST",
    body: { email, password },
  });
  return data.token;
}

export async function apiMe(): Promise<AuthUser> {
  const { data } = await api<AuthUser>("/me");
  return data;
}

export async function apiLogout(): Promise<void> {
  await api("/logout", { method: "POST" });
}
