import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { ref } from 'vue'
import {
    createCompany,
    createGate,
    createTerminal,
    createUser,
    deleteCompany,
    deleteGate,
    deleteTerminal,
    deleteUser,
    fetchAdminGates,
    fetchCompanies,
    fetchTerminals,
    fetchUsers,
    updateCompany,
    updateGate,
    updateTerminal,
    updateUser,
} from '@/api/admin'
import type { CreateGatePayload, CreateUserPayload, UpdateUserPayload } from '@/types/api'

export function useTerminals() {
    const query = useQuery({ queryKey: ['admin-terminals'], queryFn: fetchTerminals, staleTime: 0 })
    const client = useQueryClient()

    const create = useMutation({
        mutationFn: createTerminal,
        onSuccess: () => client.invalidateQueries({ queryKey: ['admin-terminals'] }),
    })

    const update = useMutation({
        mutationFn: ({ id, ...payload }: { id: number; code: string; name: string }) =>
            updateTerminal(id, payload),
        onSuccess: () => client.invalidateQueries({ queryKey: ['admin-terminals'] }),
    })

    const remove = useMutation({
        mutationFn: deleteTerminal,
        onSuccess: () => client.invalidateQueries({ queryKey: ['admin-terminals'] }),
    })

    return { ...query, create, update, remove }
}

export function useAdminGates(terminalId?: number) {
    const query = useQuery({
        queryKey: ['admin-gates', terminalId],
        queryFn: () => fetchAdminGates(terminalId),
        staleTime: 0,
    })
    const client = useQueryClient()

    const create = useMutation({
        mutationFn: (payload: CreateGatePayload) => createGate(payload),
        onSuccess: () => client.invalidateQueries({ queryKey: ['admin-gates'] }),
    })

    const update = useMutation({
        mutationFn: ({ id, ...payload }: { id: number } & CreateGatePayload) =>
            updateGate(id, payload),
        onSuccess: () => client.invalidateQueries({ queryKey: ['admin-gates'] }),
    })

    const remove = useMutation({
        mutationFn: deleteGate,
        onSuccess: () => client.invalidateQueries({ queryKey: ['admin-gates'] }),
    })

    return { ...query, create, update, remove }
}

export function useCompanies() {
    const query = useQuery({ queryKey: ['admin-companies'], queryFn: fetchCompanies, staleTime: 0 })
    const client = useQueryClient()

    const create = useMutation({
        mutationFn: createCompany,
        onSuccess: () => client.invalidateQueries({ queryKey: ['admin-companies'] }),
    })

    const update = useMutation({
        mutationFn: ({ id, ...payload }: { id: number; code: string; name: string }) =>
            updateCompany(id, payload),
        onSuccess: () => client.invalidateQueries({ queryKey: ['admin-companies'] }),
    })

    const remove = useMutation({
        mutationFn: deleteCompany,
        onSuccess: () => client.invalidateQueries({ queryKey: ['admin-companies'] }),
    })

    return { ...query, create, update, remove }
}

export function useUsers(role?: string) {
    const query = useQuery({
        queryKey: ['admin-users', role],
        queryFn: () => fetchUsers(role),
        staleTime: 0,
    })
    const client = useQueryClient()

    const create = useMutation({
        mutationFn: (payload: CreateUserPayload) => createUser(payload),
        onSuccess: () => client.invalidateQueries({ queryKey: ['admin-users'] }),
    })

    const update = useMutation({
        mutationFn: ({ id, ...payload }: { id: number } & UpdateUserPayload) =>
            updateUser(id, payload),
        onSuccess: () => client.invalidateQueries({ queryKey: ['admin-users'] }),
    })

    const remove = useMutation({
        mutationFn: deleteUser,
        onSuccess: () => client.invalidateQueries({ queryKey: ['admin-users'] }),
    })

    return { ...query, create, update, remove }
}

/** Kumpulkan semua referensi master data untuk dropdown di form user. */
export function useAdminRefs() {
    const terminals = useQuery({ queryKey: ['admin-terminals'], queryFn: fetchTerminals, staleTime: 30_000 })
    const companies = useQuery({ queryKey: ['admin-companies'], queryFn: fetchCompanies, staleTime: 30_000 })

    const roleNeedsTerminal = (role: string) => role === 'gate-officer'
    const roleNeedsCompany = (role: string) => ['transporter', 'driver'].includes(role)

    const selectedRole = ref('')

    return { terminals, companies, selectedRole, roleNeedsTerminal, roleNeedsCompany }
}
