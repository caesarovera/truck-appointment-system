import { api } from '@/api/client';
import type { Truck, TruckPayload } from '@/types/api';

/** Armada truk milik transporter (CRUD company-scoped). Semua unwrap `data`. */
export async function fetchMyTrucks(): Promise<Truck[]> {
    const { data } = await api.get<{ data: Truck[] }>('/me/trucks');

    return data.data;
}

export async function createTruck(payload: TruckPayload): Promise<Truck> {
    const { data } = await api.post<{ data: Truck }>('/me/trucks', payload);

    return data.data;
}

export async function updateTruck(id: number, payload: TruckPayload): Promise<Truck> {
    const { data } = await api.patch<{ data: Truck }>(`/me/trucks/${id}`, payload);

    return data.data;
}

export async function deleteTruck(id: number): Promise<void> {
    await api.delete(`/me/trucks/${id}`);
}
