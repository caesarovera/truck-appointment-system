import { api } from './client'
import type {
    AdminTerminal,
    AdminGate,
    AdminCompany,
    AdminUser,
    CreateTerminalPayload,
    CreateGatePayload,
    CreateCompanyPayload,
    CreateUserPayload,
    UpdateUserPayload,
} from '@/types/api'

// ── Terminals ──────────────────────────────────────────────────────────────

export const fetchTerminals = (): Promise<AdminTerminal[]> =>
    api.get<{ data: AdminTerminal[] }>('/admin/terminals').then((r) => r.data.data)

export const createTerminal = (payload: CreateTerminalPayload): Promise<AdminTerminal> =>
    api.post<{ data: AdminTerminal }>('/admin/terminals', payload).then((r) => r.data.data)

export const updateTerminal = (id: number, payload: CreateTerminalPayload): Promise<AdminTerminal> =>
    api.put<{ data: AdminTerminal }>(`/admin/terminals/${id}`, payload).then((r) => r.data.data)

export const deleteTerminal = (id: number): Promise<void> =>
    api.delete(`/admin/terminals/${id}`)

// ── Gates ──────────────────────────────────────────────────────────────────

export const fetchAdminGates = (terminalId?: number): Promise<AdminGate[]> =>
    api
        .get<{ data: AdminGate[] }>('/admin/gates', { params: terminalId ? { terminal: terminalId } : {} })
        .then((r) => r.data.data)

export const createGate = (payload: CreateGatePayload): Promise<AdminGate> =>
    api.post<{ data: AdminGate }>('/admin/gates', payload).then((r) => r.data.data)

export const updateGate = (id: number, payload: CreateGatePayload): Promise<AdminGate> =>
    api.put<{ data: AdminGate }>(`/admin/gates/${id}`, payload).then((r) => r.data.data)

export const deleteGate = (id: number): Promise<void> =>
    api.delete(`/admin/gates/${id}`)

// ── Companies ──────────────────────────────────────────────────────────────

export const fetchCompanies = (): Promise<AdminCompany[]> =>
    api.get<{ data: AdminCompany[] }>('/admin/companies').then((r) => r.data.data)

export const createCompany = (payload: CreateCompanyPayload): Promise<AdminCompany> =>
    api.post<{ data: AdminCompany }>('/admin/companies', payload).then((r) => r.data.data)

export const updateCompany = (id: number, payload: CreateCompanyPayload): Promise<AdminCompany> =>
    api.put<{ data: AdminCompany }>(`/admin/companies/${id}`, payload).then((r) => r.data.data)

export const deleteCompany = (id: number): Promise<void> =>
    api.delete(`/admin/companies/${id}`)

// ── Users ──────────────────────────────────────────────────────────────────

export const fetchUsers = (role?: string): Promise<AdminUser[]> =>
    api
        .get<{ data: AdminUser[] }>('/admin/users', { params: role ? { role } : {} })
        .then((r) => r.data.data)

export const createUser = (payload: CreateUserPayload): Promise<AdminUser> =>
    api.post<{ data: AdminUser }>('/admin/users', payload).then((r) => r.data.data)

export const updateUser = (id: number, payload: UpdateUserPayload): Promise<AdminUser> =>
    api.put<{ data: AdminUser }>(`/admin/users/${id}`, payload).then((r) => r.data.data)

export const deleteUser = (id: number): Promise<void> =>
    api.delete(`/admin/users/${id}`)
