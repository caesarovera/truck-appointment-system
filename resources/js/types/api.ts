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
}

/** Respons GET /slots/availability (koleksi resource → terbungkus `data`). */
export interface SlotAvailabilityResponse {
    data: SlotWindow[];
}
