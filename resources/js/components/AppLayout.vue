<script setup lang="ts">
import { RouterView } from 'vue-router';
import AppNav from '@/components/AppNav.vue';
</script>

<!--
    Layout bersama halaman ber-auth (parent route di router/index.ts).
    Halaman anak cukup merender kontennya sendiri; navbar & guard requiresAuth
    diwariskan dari sini (Vue Router menggabungkan meta parent ke child).
-->
<template>
    <div class="min-h-screen bg-gray-50">
        <AppNav />
        <RouterView />
    </div>
</template>
