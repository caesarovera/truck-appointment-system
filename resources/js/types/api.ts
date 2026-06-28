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

/** Truk (TruckResource) dari GET /me/fleet. */
export interface Truck {
    id: number;
    plate_no: string;
    status: string;
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
    status: string;
    move_type: MoveType;
    version: number;
    company_id: number;
    slot_window: SlotWindow | null;
    truck: Truck | null;
    driver: Driver | null;
    containers: Container[];
    created_at: string | null;
}
