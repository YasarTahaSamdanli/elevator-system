import {
  ArrowUpDown,
  Boxes,
  Building2,
  ClipboardList,
  FileText,
  Inbox,
  LayoutDashboard,
  QrCode,
  ShieldCheck,
  Users,
  Wallet,
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
  { to: "/inspections", label: "Periyodik Kontrol", icon: ShieldCheck },
  { to: "/inspection-imports", label: "Gelen Raporlar", icon: Inbox },
  { to: "/inventory", label: "Envanter", icon: Boxes },
  { to: "/ledger", label: "Cari Hesap", icon: Wallet },
  { to: "/contracts", label: "Sözleşmeler", icon: FileText },
  { to: "/qr-labels", label: "QR Etiketleri", icon: QrCode },
  { to: "/team", label: "Ekip", icon: Users },
];
