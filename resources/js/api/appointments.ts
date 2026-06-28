import { api } from '@/api/client';
import type { BookAppointmentPayload, BookedAppointment } from '@/types/api';

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
