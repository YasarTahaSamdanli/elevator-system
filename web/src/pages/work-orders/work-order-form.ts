import type { WorkOrderInput } from "@/api/resources";
import { blankToNull, fromLocalInput, toLocalInput } from "@/lib/forms";
import type {
  ServiceContract,
  WorkOrder,
  WorkOrderPriority,
  WorkOrderStatus,
  WorkOrderType,
} from "@/types";

/** Select sentinel: "Atanmadı" seçeneği (Radix Select boş string kabul etmez). */
export const UNASSIGNED = "__unassigned__";

export interface WorkOrderFormItem {
  id: string;
  material_uuid: string;
  quantity: string;
}

export interface WorkOrderFormValues {
  service_contract_uuid: string;
  type: WorkOrderType;
  status: WorkOrderStatus;
  priority: WorkOrderPriority;
  assigned_user_uuid: string;
  scheduled_at: string;
  started_at: string;
  completed_at: string;
  description: string;
  notes: string;
  items: WorkOrderFormItem[];
}

export const emptyForm: WorkOrderFormValues = {
  service_contract_uuid: "",
  type: "maintenance",
  status: "draft",
  priority: "normal",
  assigned_user_uuid: UNASSIGNED,
  scheduled_at: "",
  started_at: "",
  completed_at: "",
  description: "",
  notes: "",
  items: [],
};

export function formFromWorkOrder(workOrder: WorkOrder | null): WorkOrderFormValues {
  if (!workOrder) return { ...emptyForm, items: [] };

  return {
    service_contract_uuid: workOrder.service_contract_id,
    type: workOrder.type,
    status: workOrder.status,
    priority: workOrder.priority,
    assigned_user_uuid: workOrder.assigned_user?.id ?? UNASSIGNED,
    scheduled_at: toLocalInput(workOrder.scheduled_at),
    started_at: toLocalInput(workOrder.started_at),
    completed_at: toLocalInput(workOrder.completed_at),
    description: workOrder.description ?? "",
    notes: workOrder.notes ?? "",
    items: [],
  };
}

export function formToInput(values: WorkOrderFormValues): WorkOrderInput {
  return {
    service_contract_uuid: values.service_contract_uuid,
    type: values.type,
    status: values.status,
    priority: values.priority,
    assigned_user_uuid:
      values.assigned_user_uuid === UNASSIGNED ? null : values.assigned_user_uuid,
    scheduled_at: fromLocalInput(values.scheduled_at),
    started_at: fromLocalInput(values.started_at),
    completed_at: fromLocalInput(values.completed_at),
    description: blankToNull(values.description),
    notes: blankToNull(values.notes),
  };
}

export function formItemsToInput(items: WorkOrderFormItem[]): WorkOrderInput["items"] {
  return items.map((item) => ({
    material_uuid: item.material_uuid,
    quantity: Number(item.quantity),
    unit_price: null,
    note: null,
  }));
}

export function contractLabel(contract: ServiceContract): string {
  const number = contract.contract_number ?? "Sözleşme";
  return `${number} — ${contract.building_name} / ${contract.elevator_name}`;
}
