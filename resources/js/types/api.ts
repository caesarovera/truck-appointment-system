export interface AuthUser {
    id: number;
    name: string;
    email: string;
    company_id: number | null;
    terminal_id: number | null;
    roles: string[];
    permissions: string[];
}

/** Respons POST /login (flat — user TIDAK terbungkus `data`). */
export interface LoginResponse {
    token: string;
    token_type: string;
    user: AuthUser;
}

/** Respons GET /me (resource tunggal → terbungkus `data`). */
export interface MeResponse {
    data: AuthUser;
}

/** Gate dari GET /gates (GateResource). */
export interface Gate {
    id: number;
    terminal_id: number;
    code: string;
    name: string;
}

export type SlotWindowStatus = 'OPEN' | 'CLOSED';

/** Satu jendela slot dari GET /slots/availability (SlotWindowResource). */
export interface SlotWindow {
    id: number;
    gate_id: number;
    date: string; // Y-m-d
    start_time: string; // H:i:s
    end_time: string; // H:i:s
    capacity: number;
    booked_count: number;
    remaining: number;
    status: SlotWindowStatus;
    // true = window.end sudah lewat walau status masih OPEN — booking ditolak
    // 409 server-side (BookAppointmentAction); FE pakai ini untuk badge/tombol.
    ended: boolean;
    // Hanya hadir saat relasi gate di-eager-load (mis. jadwal driver).
    gate?: Gate | null;
}

/** Respons GET /slots/availability (koleksi resource → terbungkus `data`). */
export interface SlotAvailabilityResponse {
    data: SlotWindow[];
}

/** Satu window dari GET /reports/utilization (SlotUtilizationResource). */
export interface SlotUtilization {
    id: number;
    start_time: string;
    end_time: string;
    status: SlotWindowStatus;
    capacity: number;
    booked_count: number;
    remaining: number;
    completed?: number;
    no_show?: number;
    cancelled?: number;
    active?: number;
}

/** Ringkasan agregat utilisasi (meta.summary). */
export interface UtilizationSummary {
    capacity: number;
    booked: number;
    completed: number;
    no_show: number;
    cancelled: number;
    active: number;
}

/** Body POST /slots (OpenSlotWindowRequest). */
export interface OpenWindowPayload {
    gate: number;
    date: string; // Y-m-d
    start_time: string; // H:i:s
    end_time: string; // H:i:s
    capacity: number;
}

export type MoveType = 'DELIVERY' | 'RECEIVAL';

export type TruckStatus = 'ACTIVE' | 'INACTIVE';

/** Truk (TruckResource) dari GET /me/fleet & GET /me/trucks. */
export interface Truck {
    id: number;
    plate_no: string;
    status: string;
}

/** Body create/update truk (POST/PATCH /me/trucks). */
export interface TruckPayload {
    plate_no: string;
    status: TruckStatus;
}

/** Sopir (DriverResource) dari GET /me/fleet. */
export interface Driver {
    id: number;
    name: string;
}

/** Armada transporter (GET /me/fleet → data.{trucks,drivers}). */
export interface Fleet {
    trucks: Truck[];
    drivers: Driver[];
}

/** Body POST /appointments (lihat BookAppointmentRequest). */
export interface BookAppointmentPayload {
    slot_window_id: number;
    truck_id: number;
    driver_id: number;
    move_type: MoveType;
    container_no: string;
    iso_type?: string;
    size?: number;
}

/** Subset AppointmentResource yang dipakai UI setelah booking sukses. */
export interface BookedAppointment {
    id: number;
    booking_code: string;
    status: string;
    move_type: MoveType;
}

/** Kontainer (ContainerResource). */
export interface Container {
    id: number;
    container_no: string;
    iso_type: string | null;
    size: number | null;
}

// ─── Admin master data ───────────────────────────────────────────────────────

export interface AdminTerminal {
    id: number;
    code: string;
    name: string;
    gates_count?: number;
    created_at: string | null;
}

export interface AdminGate {
    id: number;
    terminal_id: number;
    terminal?: { id: number; name: string } | null;
    code: string;
    name: string;
}

/** Bentuk ringkas company di respons appointment (CompanyResource, whenLoaded). */
export interface Company {
    id: number;
    code: string;
    name: string;
}

export interface AdminCompany {
    id: number;
    code: string;
    name: string;
    users_count?: number;
    trucks_count?: number;
    created_at: string | null;
}

export interface AdminUser {
    id: number;
    name: string;
    email: string;
    role: string | null;
    terminal_id: number | null;
    terminal?: { id: number; name: string } | null;
    company_id: number | null;
    company?: { id: number; name: string } | null;
    created_at: string | null;
}

export type AdminRole = 'admin' | 'planner' | 'gate-officer' | 'transporter' | 'driver';

/** GET /admin/roles — 1 baris. `admin` selalu immutable (server-enforced, bukan cuma FE). */
export interface RoleWithPermissions {
    name: AdminRole;
    immutable: boolean;
    permissions: string[];
}

export interface CreateTerminalPayload { code: string; name: string }
export interface CreateGatePayload { terminal_id: number; code: string; name: string }
export interface CreateCompanyPayload { code: string; name: string }
export interface CreateUserPayload {
    name: string;
    email: string;
    password: string;
    role: AdminRole;
    terminal_id?: number | null;
    company_id?: number | null;
}
export interface UpdateUserPayload {
    name: string;
    email: string;
    password?: string;
    role: AdminRole;
    terminal_id?: number | null;
    company_id?: number | null;
}

// ─── Appointments ────────────────────────────────────────────────────────────

/** Appointment lengkap dari GET /me/appointments (AppointmentResource). */
export interface Appointment {
    id: number;
    booking_code: string;
    // Token QR ter-sign (BUSINESS-FLOW §3.4) — sama syarat eager-load dgn
    // gate_in_at/dwell_minutes di bawah (butuh slot_window untuk hitung TTL).
    qr_token?: string | null;
    status: string;
    move_type: MoveType;
    version: number;
    company_id: number;
    // Hadir bila relasi company di-eager-load (mis. GET /reports/gate-history —
    // planner lintas-company perlu tahu ini truk siapa).
    company?: Company | null;
    slot_window: SlotWindow | null;
    truck: Truck | null;
    driver: Driver | null;
    containers: Container[];
    // Hadir bila gateIn/gateOut di-eager-load di backend (GET /me/appointments,
    // GET /reports/gate-history, respons gate-in/gate-out). Null sebelum gate-in
    // terjadi; dwell_minutes null sampai gate-out juga terjadi.
    gate_in_at?: string | null;
    gate_out_at?: string | null;
    dwell_minutes?: number | null;
    created_at: string | null;
}
