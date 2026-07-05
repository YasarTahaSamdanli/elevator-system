import type { ServiceContract } from "@/types";

export const contracts: ServiceContract[] = [
  {
    id: "ctr_1", elevator_id: "elv_1", elevator_name: "A1 - Kuzey", building_name: "Nurol Tower",
    contract_number: "SZL-2024-0001", start_date: "2024-01-01", end_date: "2026-12-31",
    status: "active", monthly_fee: 4500, notes: null,
  },
  {
    id: "ctr_2", elevator_id: "elv_2", elevator_name: "A2 - Güney", building_name: "Nurol Tower",
    contract_number: "SZL-2024-0002", start_date: "2024-01-01", end_date: "2026-12-31",
    status: "active", monthly_fee: 4500, notes: null,
  },
  {
    id: "ctr_3", elevator_id: "elv_3", elevator_name: "Servis Asansörü", building_name: "Nurol Tower",
    contract_number: "SZL-2024-0003", start_date: "2024-01-01", end_date: "2026-07-20",
    status: "active", monthly_fee: 5200, notes: "Bitişe kısa süre kaldı — yenileme görüşülecek.",
  },
  {
    id: "ctr_4", elevator_id: "elv_5", elevator_name: "Blok A", building_name: "Palladium Residence",
    contract_number: "SZL-2023-0044", start_date: "2023-03-15", end_date: "2026-03-14",
    status: "active", monthly_fee: 3200, notes: null,
  },
  {
    id: "ctr_5", elevator_id: "elv_6", elevator_name: "Blok B", building_name: "Palladium Residence",
    contract_number: "SZL-2023-0045", start_date: "2023-03-15", end_date: "2025-03-14",
    status: "expired", monthly_fee: 3200, notes: "Yenilenmedi.",
  },
  {
    id: "ctr_6", elevator_id: "elv_7", elevator_name: "Yük Asansörü", building_name: "Palladium Residence",
    contract_number: "SZL-2023-0046", start_date: "2023-03-15", end_date: "2026-08-10",
    status: "active", monthly_fee: 6800, notes: null,
  },
  {
    id: "ctr_7", elevator_id: "elv_8", elevator_name: "P1", building_name: "Kızılay İş Merkezi",
    contract_number: "SZL-2022-0198", start_date: "2022-06-01", end_date: "2026-05-31",
    status: "suspended", monthly_fee: 3800, notes: "Ödeme gecikmesi nedeniyle askıya alındı.",
  },
  {
    id: "ctr_8", elevator_id: "elv_10", elevator_name: "Bağdat Cad. Asansörü", building_name: "Bağdat Cad. Apartmanı",
    contract_number: "SZL-2021-0071", start_date: "2021-09-01", end_date: "2027-08-31",
    status: "active", monthly_fee: 2400, notes: null,
  },
  {
    id: "ctr_9", elevator_id: "elv_11", elevator_name: "A Blok Sol", building_name: "Mavişehir Sitesi A Blok",
    contract_number: "SZL-2023-0122", start_date: "2023-01-01", end_date: "2026-07-31",
    status: "active", monthly_fee: 2900, notes: null,
  },
  {
    id: "ctr_10", elevator_id: "elv_13", elevator_name: "Kule 1", building_name: "Levent Plaza",
    contract_number: "SZL-2024-0210", start_date: "2024-05-01", end_date: "2027-04-30",
    status: "active", monthly_fee: 7500, notes: null,
  },
  {
    id: "ctr_11", elevator_id: "elv_14", elevator_name: "Kule 2", building_name: "Levent Plaza",
    contract_number: "SZL-2024-0211", start_date: "2024-05-01", end_date: "2027-04-30",
    status: "active", monthly_fee: 7500, notes: null,
  },
  {
    id: "ctr_12", elevator_id: "elv_16", elevator_name: "Panorama 1", building_name: "Bornova Metro AVM",
    contract_number: "SZL-2020-0410", start_date: "2020-11-01", end_date: "2025-10-31",
    status: "terminated", monthly_fee: 5000, notes: "Bina tadilatı nedeniyle feshedildi.",
  },
  {
    id: "ctr_13", elevator_id: "elv_17", elevator_name: "Ofis 1", building_name: "Çukurambar Ofis Kule",
    contract_number: "SZL-2023-0301", start_date: "2023-07-01", end_date: "2026-09-05",
    status: "active", monthly_fee: 4100, notes: null,
  },
  {
    id: "ctr_14", elevator_id: "elv_18", elevator_name: "Ofis 2", building_name: "Çukurambar Ofis Kule",
    contract_number: "SZL-2023-0302", start_date: "2023-07-01", end_date: "2026-09-05",
    status: "active", monthly_fee: 4100, notes: null,
  },
];
