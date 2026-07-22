/**
 * Typed fetchers for the backend resources. Raw API payloads (uuid keys,
 * nested relation objects) are mapped onto the UI domain types in
 * `@/types`, so pages and components stay unchanged.
 */
import { api, apiBlob, listQueryString, type ListParams, type PaginationMeta } from "@/lib/api";
import type {
  AccountSummary,
  AccountTransaction,
  Building,
  Elevator,
  ElevatorInspection,
  InspectionFinding,
  InspectionImport,
  Material,
  MaterialUnit,
  PaymentMethod,
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
  current_label: Elevator["current_label"];
  last_inspection_at: string | null;
  next_inspection_due: string | null;
  follow_up_due: string | null;
  notes: string | null;
}

interface InspectionFindingPayload {
  uuid: string;
  description: string;
  is_resolved: boolean;
}

interface ElevatorInspectionPayload {
  uuid: string;
  elevator: Ref & { serial_number: string | null; building: Ref };
  type: ElevatorInspection["type"];
  inspection_body: string | null;
  inspected_at: string;
  label: ElevatorInspection["label"];
  report_number: string | null;
  follow_up_due_date: string | null;
  next_inspection_date: string | null;
  work_order: { uuid: string; work_order_number: string; status: WorkOrder["status"] } | null;
  findings?: InspectionFindingPayload[];
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
  severity: "red" | "yellow" | "blue" | null;
  item_code: string | null;
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
  default_sale_price: string | number | null;
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

interface PaymentMethodPayload {
  uuid: string;
  name: string;
  is_active: boolean;
}

interface AccountTransactionPayload {
  uuid: string;
  building: Ref;
  elevator: { uuid: string; name: string | null; serial_number: string | null } | null;
  type: AccountTransaction["type"];
  amount: string | number;
  signed_amount: string | number;
  occurred_at: string;
  work_order: { uuid: string; work_order_number: string } | null;
  payment_method: { uuid: string; name: string } | null;
  collected_by: { uuid: string; name: string } | null;
  payer_name: string | null;
  description: string | null;
  created_at: string;
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
    current_label: p.current_label,
    last_inspection_at: p.last_inspection_at,
    next_inspection_due: p.next_inspection_due,
    follow_up_due: p.follow_up_due,
    notes: p.notes,
  };
}

function mapInspectionFinding(p: InspectionFindingPayload): InspectionFinding {
  return { id: p.uuid, description: p.description, is_resolved: p.is_resolved };
}

function mapInspection(p: ElevatorInspectionPayload): ElevatorInspection {
  return {
    id: p.uuid,
    elevator_id: p.elevator.uuid ?? "",
    elevator_name: p.elevator.name ?? p.elevator.serial_number ?? "—",
    building_name: p.elevator.building.name ?? "—",
    type: p.type,
    inspection_body: p.inspection_body,
    inspected_at: p.inspected_at,
    label: p.label,
    report_number: p.report_number,
    follow_up_due_date: p.follow_up_due_date,
    next_inspection_date: p.next_inspection_date,
    work_order: p.work_order
      ? { id: p.work_order.uuid, work_order_number: p.work_order.work_order_number, status: p.work_order.status }
      : null,
    findings: (p.findings ?? []).map(mapInspectionFinding),
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
      severity: item.severity ?? null,
      item_code: item.item_code ?? null,
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
    default_sale_price: num(p.default_sale_price),
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

function mapPaymentMethod(p: PaymentMethodPayload): PaymentMethod {
  return { id: p.uuid, name: p.name, is_active: p.is_active };
}

function mapAccountTransaction(p: AccountTransactionPayload): AccountTransaction {
  return {
    id: p.uuid,
    building: { id: p.building.uuid ?? "", name: p.building.name ?? "—" },
    elevator: p.elevator
      ? { id: p.elevator.uuid, name: p.elevator.name, serial_number: p.elevator.serial_number }
      : null,
    type: p.type,
    amount: Number(p.amount),
    signed_amount: Number(p.signed_amount),
    occurred_at: p.occurred_at,
    work_order: p.work_order
      ? { id: p.work_order.uuid, work_order_number: p.work_order.work_order_number }
      : null,
    payment_method: p.payment_method
      ? { id: p.payment_method.uuid, name: p.payment_method.name }
      : null,
    collected_by: p.collected_by ? { id: p.collected_by.uuid, name: p.collected_by.name } : null,
    payer_name: p.payer_name,
    description: p.description,
    created_at: p.created_at,
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

export const fetchInspections = (params: ListParams = {}) =>
  fetchList<ElevatorInspectionPayload, ElevatorInspection>("/elevator-inspections", params, mapInspection);

export interface InspectionFindingInput {
  description: string;
  is_resolved?: boolean;
}

export interface InspectionInput {
  elevator_uuid?: string;
  type: ElevatorInspection["type"];
  inspection_body: string | null;
  inspected_at: string;
  label: ElevatorInspection["label"];
  report_number: string | null;
  follow_up_due_date: string | null;
  next_inspection_date: string | null;
  notes: string | null;
  findings: InspectionFindingInput[];
}

export async function createInspection(input: InspectionInput): Promise<ElevatorInspection> {
  const { data } = await api<ElevatorInspectionPayload>("/elevator-inspections", {
    method: "POST",
    body: input,
  });
  return mapInspection(data);
}

export async function updateInspection(uuid: string, input: InspectionInput): Promise<ElevatorInspection> {
  // elevator_uuid is not updatable — an inspection stays on its elevator.
  const { elevator_uuid: _elevator, ...body } = input;
  const { data } = await api<ElevatorInspectionPayload>(`/elevator-inspections/${uuid}`, {
    method: "PUT",
    body,
  });
  return mapInspection(data);
}

export async function deleteInspection(uuid: string): Promise<void> {
  await api(`/elevator-inspections/${uuid}`, { method: "DELETE" });
}

export async function updateInspectionFinding(
  inspectionUuid: string,
  findingUuid: string,
  body: { description?: string; is_resolved?: boolean }
): Promise<void> {
  await api(`/elevator-inspections/${inspectionUuid}/findings/${findingUuid}`, {
    method: "PATCH",
    body,
  });
}

/** Opens a repair work order for the inspection's unresolved findings. */
export async function createInspectionWorkOrder(inspectionUuid: string): Promise<ElevatorInspection> {
  const { data } = await api<ElevatorInspectionPayload>(
    `/elevator-inspections/${inspectionUuid}/work-order`,
    { method: "POST" }
  );
  return mapInspection(data);
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
  items?: WorkOrderItemInput[];
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
  default_sale_price: number | null;
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

export const fetchPaymentMethods = (params: ListParams = {}) =>
  fetchList<PaymentMethodPayload, PaymentMethod>("/payment-methods", params, mapPaymentMethod);

export async function createPaymentMethod(name: string): Promise<PaymentMethod> {
  const { data } = await api<PaymentMethodPayload>("/payment-methods", {
    method: "POST",
    body: { name },
  });
  return mapPaymentMethod(data);
}

export async function updatePaymentMethod(
  uuid: string,
  input: { name?: string; is_active?: boolean }
): Promise<PaymentMethod> {
  const { data } = await api<PaymentMethodPayload>(`/payment-methods/${uuid}`, {
    method: "PATCH",
    body: input,
  });
  return mapPaymentMethod(data);
}

export const fetchAccountTransactions = (params: ListParams = {}) =>
  fetchList<AccountTransactionPayload, AccountTransaction>(
    "/account-transactions",
    params,
    mapAccountTransaction
  );

export interface AccountTransactionInput {
  building_uuid: string;
  elevator_uuid?: string | null;
  type: AccountTransaction["type"];
  amount: number;
  occurred_at: string;
  payment_method_uuid?: string | null;
  payer_name?: string | null;
  description?: string | null;
}

export async function createAccountTransaction(
  input: AccountTransactionInput
): Promise<AccountTransaction> {
  const { data } = await api<AccountTransactionPayload>("/account-transactions", {
    method: "POST",
    body: input,
  });
  return mapAccountTransaction(data);
}

export async function fetchAccountSummary(params: {
  building_uuid?: string;
  elevator_uuid?: string;
  occurred_at_from?: string;
  occurred_at_to?: string;
}): Promise<AccountSummary> {
  const query = new URLSearchParams();
  for (const [key, value] of Object.entries(params)) {
    if (value) query.set(key, value);
  }
  const qs = query.toString();
  const { data } = await api<AccountSummary>(`/account-transactions/summary${qs ? `?${qs}` : ""}`);
  return data;
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

/* ---------- inspection imports (RoyalCert report pipeline) ---------- */

interface InspectionImportPayload {
  uuid: string;
  source: InspectionImport["source"];
  status: InspectionImport["status"];
  review_reason: InspectionImport["review_reason"];
  error_message: string | null;
  work_order_error: string | null;
  mail_from: string | null;
  mail_subject: string | null;
  mail_received_at: string | null;
  original_filename: string | null;
  report_number: string | null;
  parsed_payload: {
    label?: InspectionImport["parsed_label"];
    type?: InspectionImport["parsed_type"];
    identity?: string | null;
    /** Structured since the EK 7 parser; plain strings on older imports. */
    findings?: (
      | string
      | {
          severity: "red" | "yellow" | "blue";
          position: number;
          item_code: string;
          description: string;
          measurement: string | null;
        }
    )[];
    warnings?: string[];
  } | null;
  matched_via: string | null;
  elevator: (Ref & { serial_number: string | null; building: Ref }) | null;
  inspection: {
    uuid: string;
    label: ElevatorInspection["label"];
    inspected_at: string | null;
    work_order: { uuid: string; work_order_number: string; status: WorkOrder["status"] } | null;
  } | null;
  created_at: string;
}

function mapInspectionImport(p: InspectionImportPayload): InspectionImport {
  return {
    id: p.uuid,
    source: p.source,
    status: p.status,
    review_reason: p.review_reason,
    error_message: p.error_message,
    work_order_error: p.work_order_error,
    mail_from: p.mail_from,
    mail_subject: p.mail_subject,
    mail_received_at: p.mail_received_at,
    original_filename: p.original_filename,
    report_number: p.report_number,
    parsed_label: p.parsed_payload?.label ?? null,
    parsed_type: p.parsed_payload?.type ?? null,
    parsed_identity: p.parsed_payload?.identity ?? null,
    parsed_findings: (p.parsed_payload?.findings ?? []).map((f) =>
      typeof f === "string"
        ? f
        : [f.item_code, f.description, f.measurement ? `(Ölç: ${f.measurement})` : null]
            .filter(Boolean)
            .join(" ")
    ),
    parsed_warnings: p.parsed_payload?.warnings ?? [],
    matched_via: p.matched_via,
    elevator_id: p.elevator?.uuid ?? null,
    elevator_name: p.elevator ? (p.elevator.name ?? p.elevator.serial_number ?? "—") : null,
    building_name: p.elevator?.building.name ?? null,
    inspection: p.inspection
      ? {
          id: p.inspection.uuid,
          label: p.inspection.label,
          inspected_at: p.inspection.inspected_at,
          work_order: p.inspection.work_order
            ? {
                id: p.inspection.work_order.uuid,
                work_order_number: p.inspection.work_order.work_order_number,
                status: p.inspection.work_order.status,
              }
            : null,
        }
      : null,
    created_at: p.created_at,
  };
}

export const fetchInspectionImports = (params: ListParams = {}) =>
  fetchList<InspectionImportPayload, InspectionImport>(
    "/inspection-imports",
    params,
    mapInspectionImport
  );

export async function uploadInspectionImport(file: File): Promise<InspectionImport> {
  const body = new FormData();
  body.append("file", file);

  const { data } = await api<InspectionImportPayload>("/inspection-imports", {
    method: "POST",
    body,
  });
  return mapInspectionImport(data);
}

export async function matchInspectionImport(
  uuid: string,
  elevatorUuid: string
): Promise<InspectionImport> {
  const { data } = await api<InspectionImportPayload>(`/inspection-imports/${uuid}/match`, {
    method: "POST",
    body: { elevator_uuid: elevatorUuid },
  });
  return mapInspectionImport(data);
}

export async function retryInspectionImport(uuid: string): Promise<InspectionImport> {
  const { data } = await api<InspectionImportPayload>(`/inspection-imports/${uuid}/retry`, {
    method: "POST",
  });
  return mapInspectionImport(data);
}

export async function ignoreInspectionImport(uuid: string): Promise<InspectionImport> {
  const { data } = await api<InspectionImportPayload>(`/inspection-imports/${uuid}/ignore`, {
    method: "POST",
  });
  return mapInspectionImport(data);
}

export async function deleteInspectionImport(uuid: string): Promise<void> {
  await api(`/inspection-imports/${uuid}`, { method: "DELETE" });
}

/** Open the stored report PDF in a new tab (auth-protected blob). */
export async function fetchInspectionImportPdf(uuid: string): Promise<Blob> {
  return apiBlob(`/inspection-imports/${uuid}/pdf`);
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
