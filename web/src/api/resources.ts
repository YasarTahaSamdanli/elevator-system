/**
 * Typed fetchers for the backend resources. Raw API payloads (uuid keys,
 * nested relation objects) are mapped onto the UI domain types in
 * `@/types`, so pages and components stay unchanged.
 */
import { api, listQueryString, type ListParams, type PaginationMeta } from "@/lib/api";
import type {
  Building,
  Elevator,
  ServiceContract,
  User,
  UserRole,
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
}

interface UserPayload {
  uuid: string;
  name: string;
  email: string;
  phone: string | null;
  is_active: boolean;
  roles: string[];
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

export const fetchElevators = (params: ListParams = {}) =>
  fetchList<ElevatorPayload, Elevator>("/elevators", params, mapElevator);

export const fetchContracts = (params: ListParams = {}) =>
  fetchList<ContractPayload, ServiceContract>("/service-contracts", params, mapContract);

export const fetchWorkOrders = (params: ListParams = {}) =>
  fetchList<WorkOrderPayload, WorkOrder>("/work-orders", params, mapWorkOrder);

export const fetchUsers = (params: ListParams = {}) =>
  fetchList<UserPayload, User>("/users", params, mapUser);

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
