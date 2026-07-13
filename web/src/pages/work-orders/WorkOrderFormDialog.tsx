import * as React from "react";
import { Loader2, Package, Plus, Trash2 } from "lucide-react";
import { Field, FormErrorBanner } from "@/components/common/Field";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  workOrderPriorityMeta,
  workOrderStatusMeta,
  workOrderTypeMeta,
} from "@/lib/status";
import { formatNumber } from "@/lib/format";
import { fieldError, metaOptions } from "@/lib/forms";
import type { Material, ServiceContract, User, WorkOrder } from "@/types";
import type { WorkOrderPriority, WorkOrderStatus, WorkOrderType } from "@/types";
import {
  UNASSIGNED,
  contractLabel,
  formFromWorkOrder,
  type WorkOrderFormValues,
} from "./work-order-form";

const statusOptions = metaOptions<WorkOrderStatus>(workOrderStatusMeta);
const typeOptions = metaOptions<WorkOrderType>(workOrderTypeMeta);
const priorityOptions = metaOptions<WorkOrderPriority>(workOrderPriorityMeta);

export function WorkOrderFormDialog({
  open,
  workOrder,
  contracts,
  materials,
  users,
  errors,
  formError,
  isSubmitting,
  onOpenChange,
  onSubmit,
}: {
  open: boolean;
  workOrder: WorkOrder | null;
  contracts: ServiceContract[];
  materials: Material[];
  users: User[];
  errors: Record<string, string[]>;
  formError: string | null;
  isSubmitting: boolean;
  onOpenChange: (open: boolean) => void;
  onSubmit: (values: WorkOrderFormValues) => Promise<void>;
}) {
  const [values, setValues] = React.useState<WorkOrderFormValues>(() =>
    formFromWorkOrder(workOrder)
  );
  const [materialUuid, setMaterialUuid] = React.useState("");
  const [materialQuantity, setMaterialQuantity] = React.useState("1");
  const isEditing = !!workOrder;

  React.useEffect(() => {
    if (open) {
      setValues(formFromWorkOrder(workOrder));
      setMaterialUuid("");
      setMaterialQuantity("1");
    }
  }, [workOrder, open]);

  const setValue = (field: Exclude<keyof WorkOrderFormValues, "items">, value: string) => {
    setValues((prev) => ({ ...prev, [field]: value }));
  };

  const addMaterialItem = () => {
    if (!materialUuid || Number(materialQuantity) <= 0) return;

    setValues((prev) => ({
      ...prev,
      items: [
        ...prev.items,
        {
          id: `${materialUuid}-${Date.now()}`,
          material_uuid: materialUuid,
          quantity: materialQuantity,
        },
      ],
    }));
    setMaterialUuid("");
    setMaterialQuantity("1");
  };

  const removeMaterialItem = (id: string) => {
    setValues((prev) => ({
      ...prev,
      items: prev.items.filter((item) => item.id !== id),
    }));
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent
        className="max-h-[90vh] overflow-y-auto sm:max-w-2xl"
        onInteractOutside={(event) => event.preventDefault()}
      >
        <DialogHeader>
          <DialogTitle>{isEditing ? "İş Emrini Düzenle" : "Yeni İş Emri"}</DialogTitle>
          <DialogDescription>
            {isEditing ? (
              <>
                İş emri no:{" "}
                <span className="font-mono text-xs">{workOrder.work_order_number}</span>
              </>
            ) : (
              "Sözleşme, iş türü ve planlama bilgilerini gir. İş emri numarası kayıt sırasında otomatik oluşturulur."
            )}
          </DialogDescription>
        </DialogHeader>

        <form
          noValidate
          className="space-y-4"
          onSubmit={(event) => {
            event.preventDefault();
            void onSubmit(values);
          }}
        >
          <FormErrorBanner message={formError} />

          <Field label="Sözleşme" error={fieldError(errors, "service_contract_uuid")}>
            <Select
              value={values.service_contract_uuid || undefined}
              onValueChange={(value) => setValue("service_contract_uuid", value)}
            >
              <SelectTrigger>
                <SelectValue placeholder="Sözleşme seç" />
              </SelectTrigger>
              <SelectContent>
                {contracts.map((contract) => (
                  <SelectItem key={contract.id} value={contract.id}>
                    {contractLabel(contract)}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </Field>

          <div className="grid gap-4 sm:grid-cols-3">
            <Field label="Tür" error={fieldError(errors, "type")}>
              <Select value={values.type} onValueChange={(value) => setValue("type", value)}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {typeOptions.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                      {option.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </Field>
            <Field label="Durum" error={fieldError(errors, "status")}>
              <Select value={values.status} onValueChange={(value) => setValue("status", value)}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {statusOptions.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                      {option.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </Field>
            <Field label="Öncelik" error={fieldError(errors, "priority")}>
              <Select
                value={values.priority}
                onValueChange={(value) => setValue("priority", value)}
              >
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {priorityOptions.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                      {option.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </Field>
          </div>

          <Field label="Teknisyen" error={fieldError(errors, "assigned_user_uuid")}>
            <Select
              value={values.assigned_user_uuid}
              onValueChange={(value) => setValue("assigned_user_uuid", value)}
            >
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value={UNASSIGNED}>Atanmadı</SelectItem>
                {users.map((user) => (
                  <SelectItem key={user.id} value={user.id}>
                    {user.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </Field>

          <div className="grid gap-4 sm:grid-cols-3">
            <Field label="Planlanan" error={fieldError(errors, "scheduled_at")}>
              <Input
                type="datetime-local"
                value={values.scheduled_at}
                onChange={(event) => setValue("scheduled_at", event.target.value)}
              />
            </Field>
            <Field label="Başlama" error={fieldError(errors, "started_at")}>
              <Input
                type="datetime-local"
                value={values.started_at}
                onChange={(event) => setValue("started_at", event.target.value)}
              />
            </Field>
            <Field label="Tamamlanma" error={fieldError(errors, "completed_at")}>
              <Input
                type="datetime-local"
                value={values.completed_at}
                onChange={(event) => setValue("completed_at", event.target.value)}
              />
            </Field>
          </div>

          <Field label="Açıklama" error={fieldError(errors, "description")}>
            <Textarea
              value={values.description}
              onChange={(event) => setValue("description", event.target.value)}
              placeholder="Yapılacak işin detayları..."
            />
          </Field>

          {!isEditing && (
            <section className="space-y-3 rounded-md border border-border p-3">
              <div className="flex items-baseline justify-between gap-3">
                <div className="text-sm font-medium text-foreground">Malzemeler</div>
                {values.items.length > 0 && (
                  <span className="text-xs tabular-nums text-muted-foreground">
                    {values.items.length} satır
                  </span>
                )}
              </div>

              {values.items.length > 0 && (
                <div className="divide-y divide-border rounded-md border border-border">
                  {values.items.map((item) => {
                    const material = materials.find((option) => option.id === item.material_uuid);

                    return (
                      <div key={item.id} className="flex items-center gap-3 px-3 py-2.5">
                        <Package className="size-4 shrink-0 text-muted-foreground" />
                        <div className="min-w-0 flex-1">
                          <div className="truncate text-sm font-medium text-foreground">
                            {material?.name ?? "Malzeme"}
                          </div>
                          <div className="text-xs text-muted-foreground">
                            {material?.code ?? item.material_uuid} ·{" "}
                            {formatNumber(Number(item.quantity))} {material?.unit ?? ""}
                          </div>
                        </div>
                        <Button
                          type="button"
                          variant="ghost"
                          size="icon-sm"
                          aria-label="Malzeme satırını sil"
                          onClick={() => removeMaterialItem(item.id)}
                        >
                          <Trash2 className="size-4" />
                        </Button>
                      </div>
                    );
                  })}
                </div>
              )}

              <div className="grid gap-2 sm:grid-cols-[1fr_5rem_auto]">
                <Select value={materialUuid || undefined} onValueChange={setMaterialUuid}>
                  <SelectTrigger>
                    <SelectValue placeholder="Malzeme seç" />
                  </SelectTrigger>
                  <SelectContent>
                    {materials.map((material) => (
                      <SelectItem key={material.id} value={material.id}>
                        {material.code} · {material.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <Input
                  type="number"
                  min="0.001"
                  step="0.001"
                  value={materialQuantity}
                  onChange={(event) => setMaterialQuantity(event.target.value)}
                />
                <Button
                  type="button"
                  variant="outline"
                  disabled={!materialUuid || Number(materialQuantity) <= 0}
                  onClick={addMaterialItem}
                >
                  <Plus />
                  Ekle
                </Button>
              </div>
            </section>
          )}

          <Field label="Notlar" error={fieldError(errors, "notes")}>
            <Textarea
              value={values.notes}
              onChange={(event) => setValue("notes", event.target.value)}
            />
          </Field>

          <DialogFooter>
            <Button
              type="button"
              variant="outline"
              disabled={isSubmitting}
              onClick={() => onOpenChange(false)}
            >
              Vazgeç
            </Button>
            <Button type="submit" disabled={isSubmitting}>
              {isSubmitting && <Loader2 className="animate-spin" />}
              {isEditing ? "Kaydet" : "İş Emri Oluştur"}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
