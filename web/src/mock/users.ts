import type { User } from "@/types";

export const currentUser: User = {
  id: "usr_owner",
  company_id: "cmp_1",
  name: "Yaşar Taha Şamdanlı",
  email: "yasar@asansor.com",
  phone: "+90 532 000 00 00",
  role: "Company Owner",
  is_active: true,
  avatar_url: null,
};

export const users: User[] = [
  currentUser,
  {
    id: "usr_1",
    company_id: "cmp_1",
    name: "Mehmet Kaya",
    email: "mehmet.kaya@asansor.com",
    phone: "+90 533 111 11 11",
    role: "Technician",
    is_active: true,
    avatar_url: null,
  },
  {
    id: "usr_2",
    company_id: "cmp_1",
    name: "Ayşe Demir",
    email: "ayse.demir@asansor.com",
    phone: "+90 534 222 22 22",
    role: "Technician",
    is_active: true,
    avatar_url: null,
  },
  {
    id: "usr_3",
    company_id: "cmp_1",
    name: "Emre Yıldız",
    email: "emre.yildiz@asansor.com",
    phone: "+90 535 333 33 33",
    role: "Technician",
    is_active: true,
    avatar_url: null,
  },
  {
    id: "usr_4",
    company_id: "cmp_1",
    name: "Zeynep Aksoy",
    email: "zeynep.aksoy@asansor.com",
    phone: "+90 536 444 44 44",
    role: "Office Staff",
    is_active: true,
    avatar_url: null,
  },
  {
    id: "usr_5",
    company_id: "cmp_1",
    name: "Burak Şahin",
    email: "burak.sahin@asansor.com",
    phone: "+90 537 555 55 55",
    role: "Manager",
    is_active: true,
    avatar_url: null,
  },
];

export const technicians = users.filter((u) => u.role === "Technician");
