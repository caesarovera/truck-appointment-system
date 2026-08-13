<script setup lang="ts">
import { ref, watch } from 'vue'
import { useTerminals, useAdminGates, useCompanies, useUsers, useAdminRefs, useRoles } from '@/composables/useAdmin'
import type { AdminTerminal, AdminGate, AdminCompany, AdminUser, AdminRole } from '@/types/api'
import SkeletonRows from '@/components/SkeletonRows.vue'

type Tab = 'terminals' | 'gates' | 'companies' | 'users' | 'roles'
const activeTab = ref<Tab>('terminals')

// ─── Terminals ──────────────────────────────────────────────────────────────
const { data: terminals, isLoading: loadingTerminals, create: createT, update: updateT, remove: removeT } = useTerminals()
const tForm = ref({ code: '', name: '' })
const tEditId = ref<number | null>(null)

function tEdit(item: AdminTerminal) {
    tEditId.value = item.id
    tForm.value = { code: item.code, name: item.name }
}
function tCancel() { tEditId.value = null; tForm.value = { code: '', name: '' } }
async function tSubmit() {
    if (tEditId.value) await updateT.mutateAsync({ id: tEditId.value, ...tForm.value })
    else await createT.mutateAsync(tForm.value)
    tCancel()
}
async function tDelete(id: number) {
    if (!confirm('Hapus terminal ini?')) return
    await removeT.mutateAsync(id).catch((e) => alert(e.response?.data?.message ?? 'Gagal'))
}

// ─── Gates ──────────────────────────────────────────────────────────────────
const { data: gates, isLoading: loadingGates, create: createG, update: updateG, remove: removeG } = useAdminGates()
const { data: terminalRefs } = useTerminals()
const gForm = ref({ terminal_id: 0, code: '', name: '' })
const gEditId = ref<number | null>(null)

function gEdit(item: AdminGate) {
    gEditId.value = item.id
    gForm.value = { terminal_id: item.terminal_id, code: item.code, name: item.name }
}
function gCancel() { gEditId.value = null; gForm.value = { terminal_id: 0, code: '', name: '' } }
async function gSubmit() {
    if (gEditId.value) await updateG.mutateAsync({ id: gEditId.value, ...gForm.value })
    else await createG.mutateAsync(gForm.value)
    gCancel()
}
async function gDelete(id: number) {
    if (!confirm('Hapus gate ini?')) return
    await removeG.mutateAsync(id).catch((e) => alert(e.response?.data?.message ?? 'Gagal'))
}

// ─── Companies ──────────────────────────────────────────────────────────────
const { data: companies, isLoading: loadingCompanies, create: createC, update: updateC, remove: removeC } = useCompanies()
const cForm = ref({ code: '', name: '' })
const cEditId = ref<number | null>(null)

function cEdit(item: AdminCompany) {
    cEditId.value = item.id
    cForm.value = { code: item.code, name: item.name }
}
function cCancel() { cEditId.value = null; cForm.value = { code: '', name: '' } }
async function cSubmit() {
    if (cEditId.value) await updateC.mutateAsync({ id: cEditId.value, ...cForm.value })
    else await createC.mutateAsync(cForm.value)
    cCancel()
}
async function cDelete(id: number) {
    if (!confirm('Hapus perusahaan ini?')) return
    await removeC.mutateAsync(id).catch((e) => alert(e.response?.data?.message ?? 'Gagal'))
}

// ─── Users ──────────────────────────────────────────────────────────────────
const { data: users, isLoading: loadingUsers, create: createU, update: updateU, remove: removeU } = useUsers()
const { terminals: adminTerminals, companies: adminCompanies } = useAdminRefs()
const ROLES: AdminRole[] = ['admin', 'planner', 'gate-officer', 'transporter', 'driver']

const uForm = ref({
    name: '', email: '', password: '', role: '' as AdminRole,
    terminal_id: null as number | null,
    company_id: null as number | null,
})
const uEditId = ref<number | null>(null)

function uEdit(item: AdminUser) {
    uEditId.value = item.id
    uForm.value = {
        name: item.name,
        email: item.email,
        password: '',
        role: (item.role ?? 'driver') as AdminRole,
        terminal_id: item.terminal_id,
        company_id: item.company_id,
    }
}
function uCancel() {
    uEditId.value = null
    uForm.value = { name: '', email: '', password: '', role: '' as AdminRole, terminal_id: null, company_id: null }
}
async function uSubmit() {
    const payload = { ...uForm.value }
    if (!payload.password) delete (payload as { password?: string }).password
    if (uEditId.value) await updateU.mutateAsync({ id: uEditId.value, ...payload })
    else await createU.mutateAsync(payload as any)
    uCancel()
}
async function uDelete(id: number) {
    if (!confirm('Hapus user ini?')) return
    await removeU.mutateAsync(id).catch((e) => alert(e.response?.data?.message ?? 'Gagal'))
}

const needsTerminal = (role: string) => role === 'gate-officer'
const needsCompany = (role: string) => ['transporter', 'driver'].includes(role)

// ─── Role & Izin ──────────────────────────────────────────────────────────
const { data: roleData, isLoading: loadingRoles, updatePermissions } = useRoles()
// Salinan lokal yang bisa dicentang bebas — disinkronkan ulang tiap query
// server berubah (mis. setelah Simpan sukses → invalidate → refetch).
const editing = ref<Record<string, string[]>>({})
watch(
    roleData,
    (val) => {
        if (!val) return
        for (const r of val.roles) editing.value[r.name] = [...r.permissions]
    },
    { immediate: true },
)

function isChecked(roleName: string, perm: string): boolean {
    return editing.value[roleName]?.includes(perm) ?? false
}
function togglePermission(roleName: string, perm: string): void {
    const current = editing.value[roleName] ?? []
    editing.value[roleName] = current.includes(perm)
        ? current.filter((p) => p !== perm)
        : [...current, perm]
}
async function saveRolePermissions(roleName: string): Promise<void> {
    await updatePermissions.mutateAsync({ name: roleName, permissions: editing.value[roleName] ?? [] })
}
</script>

<template>
    <div class="p-6 max-w-5xl mx-auto">
        <h1 class="text-2xl font-bold mb-6">Admin — Master Data</h1>

        <!-- Tab nav -->
        <div class="flex gap-2 border-b mb-6">
            <button
                v-for="tab in (['terminals', 'gates', 'companies', 'users', 'roles'] as Tab[])"
                :key="tab"
                :class="['px-4 py-2 text-sm font-medium capitalize border-b-2 -mb-px', activeTab === tab ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-600 hover:text-gray-900']"
                @click="activeTab = tab"
            >{{ tab }}</button>
        </div>

        <!-- ─── Terminals ─────────────────────────────────────────────────── -->
        <div v-if="activeTab === 'terminals'">
            <form class="flex gap-2 mb-4" @submit.prevent="tSubmit">
                <input v-model="tForm.code" placeholder="Kode (maks 20)" maxlength="20" required class="border rounded px-2 py-1 w-28" />
                <input v-model="tForm.name" placeholder="Nama terminal" required class="border rounded px-2 py-1 flex-1" />
                <button type="submit" class="px-3 py-1 bg-blue-600 text-white rounded text-sm">
                    {{ tEditId ? 'Update' : 'Tambah' }}
                </button>
                <button v-if="tEditId" type="button" class="px-3 py-1 bg-gray-200 rounded text-sm" @click="tCancel">Batal</button>
            </form>

            <SkeletonRows v-if="loadingTerminals" :rows="3" label="Memuat terminal…" />
            <table v-else class="w-full text-sm border-collapse">
                <thead><tr class="bg-gray-100 text-left">
                    <th class="p-2 border">ID</th>
                    <th class="p-2 border">Kode</th>
                    <th class="p-2 border">Nama</th>
                    <th class="p-2 border">Gates</th>
                    <th class="p-2 border">Aksi</th>
                </tr></thead>
                <tbody>
                    <tr v-for="t in terminals" :key="t.id" class="hover:bg-gray-50">
                        <td class="p-2 border text-gray-500">{{ t.id }}</td>
                        <td class="p-2 border font-mono">{{ t.code }}</td>
                        <td class="p-2 border">{{ t.name }}</td>
                        <td class="p-2 border">{{ t.gates_count ?? '-' }}</td>
                        <td class="p-2 border">
                            <button class="text-blue-600 mr-3 text-xs" @click="tEdit(t)">Edit</button>
                            <button class="text-red-600 text-xs" @click="tDelete(t.id)">Hapus</button>
                        </td>
                    </tr>
                    <tr v-if="!terminals?.length"><td colspan="5" class="p-4 text-center text-gray-400">Belum ada terminal</td></tr>
                </tbody>
            </table>
        </div>

        <!-- ─── Gates ─────────────────────────────────────────────────────── -->
        <div v-if="activeTab === 'gates'">
            <form class="flex gap-2 mb-4" @submit.prevent="gSubmit">
                <select v-model.number="gForm.terminal_id" required class="border rounded px-2 py-1 w-44">
                    <option :value="0" disabled>-- Terminal --</option>
                    <option v-for="t in terminalRefs" :key="t.id" :value="t.id">{{ t.code }} — {{ t.name }}</option>
                </select>
                <input v-model="gForm.code" placeholder="Kode gate" maxlength="20" required class="border rounded px-2 py-1 w-24" />
                <input v-model="gForm.name" placeholder="Nama gate" required class="border rounded px-2 py-1 flex-1" />
                <button type="submit" class="px-3 py-1 bg-blue-600 text-white rounded text-sm">
                    {{ gEditId ? 'Update' : 'Tambah' }}
                </button>
                <button v-if="gEditId" type="button" class="px-3 py-1 bg-gray-200 rounded text-sm" @click="gCancel">Batal</button>
            </form>

            <SkeletonRows v-if="loadingGates" :rows="3" label="Memuat gate…" />
            <table v-else class="w-full text-sm border-collapse">
                <thead><tr class="bg-gray-100 text-left">
                    <th class="p-2 border">ID</th>
                    <th class="p-2 border">Terminal</th>
                    <th class="p-2 border">Kode</th>
                    <th class="p-2 border">Nama</th>
                    <th class="p-2 border">Aksi</th>
                </tr></thead>
                <tbody>
                    <tr v-for="g in gates" :key="g.id" class="hover:bg-gray-50">
                        <td class="p-2 border text-gray-500">{{ g.id }}</td>
                        <td class="p-2 border text-gray-500">{{ g.terminal?.name ?? g.terminal_id }}</td>
                        <td class="p-2 border font-mono">{{ g.code }}</td>
                        <td class="p-2 border">{{ g.name }}</td>
                        <td class="p-2 border">
                            <button class="text-blue-600 mr-3 text-xs" @click="gEdit(g)">Edit</button>
                            <button class="text-red-600 text-xs" @click="gDelete(g.id)">Hapus</button>
                        </td>
                    </tr>
                    <tr v-if="!gates?.length"><td colspan="5" class="p-4 text-center text-gray-400">Belum ada gate</td></tr>
                </tbody>
            </table>
        </div>

        <!-- ─── Companies ─────────────────────────────────────────────────── -->
        <div v-if="activeTab === 'companies'">
            <form class="flex gap-2 mb-4" @submit.prevent="cSubmit">
                <input v-model="cForm.code" placeholder="Kode (maks 20)" maxlength="20" required class="border rounded px-2 py-1 w-28" />
                <input v-model="cForm.name" placeholder="Nama perusahaan" required class="border rounded px-2 py-1 flex-1" />
                <button type="submit" class="px-3 py-1 bg-blue-600 text-white rounded text-sm">
                    {{ cEditId ? 'Update' : 'Tambah' }}
                </button>
                <button v-if="cEditId" type="button" class="px-3 py-1 bg-gray-200 rounded text-sm" @click="cCancel">Batal</button>
            </form>

            <SkeletonRows v-if="loadingCompanies" :rows="3" label="Memuat perusahaan…" />
            <table v-else class="w-full text-sm border-collapse">
                <thead><tr class="bg-gray-100 text-left">
                    <th class="p-2 border">ID</th>
                    <th class="p-2 border">Kode</th>
                    <th class="p-2 border">Nama</th>
                    <th class="p-2 border">Users</th>
                    <th class="p-2 border">Truk</th>
                    <th class="p-2 border">Aksi</th>
                </tr></thead>
                <tbody>
                    <tr v-for="c in companies" :key="c.id" class="hover:bg-gray-50">
                        <td class="p-2 border text-gray-500">{{ c.id }}</td>
                        <td class="p-2 border font-mono">{{ c.code }}</td>
                        <td class="p-2 border">{{ c.name }}</td>
                        <td class="p-2 border">{{ c.users_count ?? '-' }}</td>
                        <td class="p-2 border">{{ c.trucks_count ?? '-' }}</td>
                        <td class="p-2 border">
                            <button class="text-blue-600 mr-3 text-xs" @click="cEdit(c)">Edit</button>
                            <button class="text-red-600 text-xs" @click="cDelete(c.id)">Hapus</button>
                        </td>
                    </tr>
                    <tr v-if="!companies?.length"><td colspan="6" class="p-4 text-center text-gray-400">Belum ada perusahaan</td></tr>
                </tbody>
            </table>
        </div>

        <!-- ─── Users ─────────────────────────────────────────────────────── -->
        <div v-if="activeTab === 'users'">
            <form class="grid grid-cols-2 gap-2 mb-4 p-3 border rounded bg-gray-50" @submit.prevent="uSubmit">
                <input v-model="uForm.name" placeholder="Nama" required class="border rounded px-2 py-1" />
                <input v-model="uForm.email" type="email" placeholder="Email" required class="border rounded px-2 py-1" />
                <input v-model="uForm.password" type="password" :placeholder="uEditId ? 'Password (kosongkan jika tidak diubah)' : 'Password'" :required="!uEditId" class="border rounded px-2 py-1" />
                <select v-model="uForm.role" required class="border rounded px-2 py-1">
                    <option value="" disabled>-- Role --</option>
                    <option v-for="r in ROLES" :key="r" :value="r">{{ r }}</option>
                </select>
                <select v-if="needsTerminal(uForm.role)" v-model.number="uForm.terminal_id" class="border rounded px-2 py-1">
                    <option :value="null">-- Terminal --</option>
                    <option v-for="t in adminTerminals.data.value" :key="t.id" :value="t.id">{{ t.code }} — {{ t.name }}</option>
                </select>
                <select v-if="needsCompany(uForm.role)" v-model.number="uForm.company_id" class="border rounded px-2 py-1">
                    <option :value="null">-- Perusahaan --</option>
                    <option v-for="c in adminCompanies.data.value" :key="c.id" :value="c.id">{{ c.code }} — {{ c.name }}</option>
                </select>
                <div class="col-span-2 flex gap-2">
                    <button type="submit" class="px-3 py-1 bg-blue-600 text-white rounded text-sm">
                        {{ uEditId ? 'Update User' : 'Tambah User' }}
                    </button>
                    <button v-if="uEditId" type="button" class="px-3 py-1 bg-gray-200 rounded text-sm" @click="uCancel">Batal</button>
                </div>
            </form>

            <SkeletonRows v-if="loadingUsers" :rows="3" label="Memuat user…" />
            <table v-else class="w-full text-sm border-collapse">
                <thead><tr class="bg-gray-100 text-left">
                    <th class="p-2 border">ID</th>
                    <th class="p-2 border">Nama</th>
                    <th class="p-2 border">Email</th>
                    <th class="p-2 border">Role</th>
                    <th class="p-2 border">Terminal / Perusahaan</th>
                    <th class="p-2 border">Aksi</th>
                </tr></thead>
                <tbody>
                    <tr v-for="u in users" :key="u.id" class="hover:bg-gray-50">
                        <td class="p-2 border text-gray-500">{{ u.id }}</td>
                        <td class="p-2 border">{{ u.name }}</td>
                        <td class="p-2 border text-gray-600 text-xs">{{ u.email }}</td>
                        <td class="p-2 border">
                            <span class="px-1.5 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-700">{{ u.role }}</span>
                        </td>
                        <td class="p-2 border text-gray-500 text-xs">
                            {{ u.terminal?.name ?? u.company?.name ?? '—' }}
                        </td>
                        <td class="p-2 border">
                            <button class="text-blue-600 mr-3 text-xs" @click="uEdit(u)">Edit</button>
                            <button class="text-red-600 text-xs" @click="uDelete(u.id)">Hapus</button>
                        </td>
                    </tr>
                    <tr v-if="!users?.length"><td colspan="6" class="p-4 text-center text-gray-400">Belum ada user</td></tr>
                </tbody>
            </table>
        </div>

        <!-- ─── Role & Izin ───────────────────────────────────────────────── -->
        <div v-if="activeTab === 'roles'">
            <p class="text-sm text-gray-500 mb-4">
                Centang permission yang dipegang tiap role, lalu klik Simpan per baris.
                Role <strong>admin</strong> selalu punya semua permission dan tak bisa diubah.
            </p>

            <SkeletonRows v-if="loadingRoles" :rows="5" label="Memuat role…" />
            <div v-else class="space-y-4" data-testid="role-list">
                <div
                    v-for="r in roleData?.roles"
                    :key="r.name"
                    class="border rounded p-3"
                    data-testid="role-row"
                >
                    <div class="flex items-center justify-between mb-2">
                        <span class="font-medium">{{ r.name }}</span>
                        <span v-if="r.immutable" class="text-xs text-gray-400">(tak bisa diubah)</span>
                        <button
                            v-else
                            type="button"
                            :disabled="updatePermissions.isPending.value"
                            class="px-3 py-1 bg-blue-600 text-white rounded text-xs disabled:opacity-50"
                            data-testid="save-role"
                            @click="saveRolePermissions(r.name)"
                        >
                            {{ updatePermissions.isPending.value ? 'Menyimpan…' : 'Simpan' }}
                        </button>
                    </div>
                    <div class="flex flex-wrap gap-x-4 gap-y-1">
                        <label
                            v-for="perm in roleData?.allPermissions"
                            :key="perm"
                            class="flex items-center gap-1 text-xs"
                        >
                            <input
                                type="checkbox"
                                :disabled="r.immutable"
                                :checked="isChecked(r.name, perm)"
                                @change="togglePermission(r.name, perm)"
                            />
                            {{ perm }}
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
