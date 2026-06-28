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
