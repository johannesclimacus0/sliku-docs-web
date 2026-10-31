<script setup lang="ts">
import SettingsSection from '@/components/ui/SettingsSection.vue'
import PageHeading from '@/components/ui/PageHeading.vue'
import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { dateTime } from '@/services/account'
import ProfileForm from '@/components/ProfileForm.vue'
import AvatarEditor from '@/components/AvatarEditor.vue'

const auth = useAuthStore()
const verifiedDate = computed(() => {
    const value = auth.user?.emailVerifiedAt
    return value
        ? new Intl.DateTimeFormat('ru-RU', {
              day: 'numeric',
              month: 'long',
              year: 'numeric',
          }).format(new Date(value))
        : null
})
</script>
<template>
    <main v-if="auth.user">
        <PageHeading title="Мой профиль"></PageHeading>
        <SettingsSection title="Фотография"><AvatarEditor /></SettingsSection>
        <SettingsSection title="Личные данные">
            <ProfileForm :user="auth.user" :disabled="auth.user.emailVerificationRequired" />
        </SettingsSection>
        <SettingsSection title="Аккаунт">
            <dl
                class="mt-0 mb-5 [&>div]:grid [&>div]:grid-cols-[150px_minmax(0,1fr)] [&>div]:gap-[14px] [&>div]:py-2 [&_dt]:text-[13px] [&_dt]:text-muted [&_dd]:m-0 [&_dd]:[overflow-wrap:anywhere] max-[680px]:[&>div]:grid-cols-1 max-[680px]:[&>div]:gap-[3px]">
                <div>
                    <dt>Email</dt>
                    <dd>
                        {{ auth.user.email
                        }}<small class="mt-1 block text-muted">{{ verifiedDate ? `Подтверждён ${verifiedDate}` : 'Не подтверждён' }}</small>
                    </dd>
                </div>
                <div>
                    <dt>Роль</dt>
                    <dd>{{ auth.user.role === 'admin' ? 'Администратор' : 'Участник' }}</dd>
                </div>
                <div>
                    <dt>Дата регистрации</dt>
                    <dd>{{ dateTime(auth.user.createdAt) }}</dd>
                </div>
                <div>
                    <dt>Последнее изменение</dt>
                    <dd>{{ dateTime(auth.user.updatedAt) }}</dd>
                </div>
            </dl>
            <RouterLink to="/forgot-password" class="text-[13px] underline underline-offset-[3px]">Восстановить пароль</RouterLink>
        </SettingsSection>
    </main>
</template>
