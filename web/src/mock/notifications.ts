import type { AppNotification } from "@/types";

export const notifications: AppNotification[] = [
  {
    id: "ntf_1",
    title: "Kritik arıza bildirimi",
    body: "Nurol Tower · A2 - Güney asansörü 12. katta askıda kaldı.",
    type: "work_order",
    created_at: "2026-07-05T08:32:00Z",
    read: false,
  },
  {
    id: "ntf_2",
    title: "Muayene son günü",
    body: "Kızılay İş Merkezi · P1 için zorunlu yıllık muayene bugün.",
    type: "work_order",
    created_at: "2026-07-05T07:00:00Z",
    read: false,
  },
  {
    id: "ntf_3",
    title: "Sözleşme bitişi yaklaşıyor",
    body: "Nurol Tower · Servis Asansörü sözleşmesi 15 gün içinde sona eriyor.",
    type: "contract",
    created_at: "2026-07-04T16:20:00Z",
    read: false,
  },
  {
    id: "ntf_4",
    title: "İş emri tamamlandı",
    body: "Palladium Residence · Blok A periyodik bakımı tamamlandı.",
    type: "work_order",
    created_at: "2026-07-01T10:22:00Z",
    read: true,
  },
  {
    id: "ntf_5",
    title: "Asansör servis dışı",
    body: "Palladium Residence · Blok B ana motor arızası nedeniyle servis dışı.",
    type: "elevator",
    created_at: "2026-06-30T09:15:00Z",
    read: true,
  },
];
