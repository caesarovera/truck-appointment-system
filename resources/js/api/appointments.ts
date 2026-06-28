import { api } from '@/api/client';
import type { Appointment, BookAppointmentPayload, BookedAppointment } from '@/types/api';

/** Daftar booking milik transporter — GET /me/appointments (filter status opsional). */
export async function fetchMyAppointments(status?: string): Promise<Appointment[]> {
    const params: Record<string, string> = {};
    if (status !== undefined && status !== '') {
        params.status = status;
    }

    const { data } = await api.get<{ data: Appointment[] }>('/me/appointments', { params });

    return data.data;
}

/** Batalkan appointment — kirim `version` untuk optimistic lock (409 bila usang). */
export async function cancelAppointment(id: number, version: number): Promise<Appointment> {
    const { data } = await api.post<{ data: Appointment }>(`/appointments/${id}/cancel`, { version });

    return data.data;
}

/** Pindahkan appointment ke window lain — `version` untuk optimistic lock. */
export async function rescheduleAppointment(
    id: number,
    slotWindowId: number,
    version: number,
): Promise<Appointment> {
    const { data } = await api.post<{ data: Appointment }>(`/appointments/${id}/reschedule`, {
        slot_window_id: slotWindowId,
        version,
    });

    return data.data;
}

/**
 * Booking slot — POST /appointments. Kirim Idempotency-Key (mobile/double-tap
 * sering kirim ganda → server putar ulang respons yang sama). Unwrap `data`.
 */
export async function bookAppointment(
    payload: BookAppointmentPayload,
    idempotencyKey: string,
): Promise<BookedAppointment> {
    const { data } = await api.post<{ data: BookedAppointment }>('/appointments', payload, {
        headers: { 'Idempotency-Key': idempotencyKey },
    });

    return data.data;
}
