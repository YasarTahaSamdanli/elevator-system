import {
  ArrowUpDown,
  Building2,
  ClipboardList,
  FileText,
  LayoutDashboard,
  Users,
  type LucideIcon,
} from "lucide-react";

export interface NavItem {
  to: string;
  label: string;
  icon: LucideIcon;
}

export const navItems: NavItem[] = [
  { to: "/dashboard", label: "Gösterge Paneli", icon: LayoutDashboard },
  { to: "/buildings", label: "Binalar", icon: Building2 },
  { to: "/elevators", label: "Asansörler", icon: ArrowUpDown },
  { to: "/work-orders", label: "İş Emirleri", icon: ClipboardList },
  { to: "/contracts", label: "Sözleşmeler", icon: FileText },
  { to: "/team", label: "Ekip", icon: Users },
];
