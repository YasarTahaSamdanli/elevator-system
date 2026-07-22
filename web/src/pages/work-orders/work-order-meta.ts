import {
  AlertTriangle,
  ClipboardCheck,
  Hammer,
  Wrench,
  type LucideIcon,
} from "lucide-react";
import type { WorkOrderType } from "@/types";

export const typeIcons: Record<WorkOrderType, LucideIcon> = {
  maintenance: Wrench,
  fault: AlertTriangle,
  inspection: ClipboardCheck,
  repair: Hammer,
};
